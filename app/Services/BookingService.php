<?php

namespace App\Services;

use App\Enums\BookingStatus as S;
use App\Events\BookingSubmitted;
use App\Models\Booking;
use App\Models\User;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * Création atomique : le verrou (lockForUpdate) sur la villa sérialise les réservations
     * concurrentes -> impossible de réserver deux fois les mêmes dates (anti double-booking).
     */
    public function create(User $user, array $data): Booking
    {
        $booking = DB::transaction(function () use ($user, $data) {
            $villa = Villa::approved()->whereKey($data['villa_id'])->lockForUpdate()->firstOrFail();
            $in = Carbon::parse($data['check_in']);
            $out = Carbon::parse($data['check_out']);

            if ((int) $data['guests'] > $villa->capacity) {
                throw ValidationException::withMessages(['guests' => "Capacité maximale : {$villa->capacity} voyageurs."]);
            }

            $conflict = Booking::where('villa_id', $villa->id)
                ->where('status', '!=', S::Cancelled->value)
                ->where('check_in', '<', $out)
                ->where('check_out', '>', $in)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages(['check_in' => 'Villa indisponible sur ces dates.']);
            }

            $quote = $this->pricing->quote($villa, $in, $out, $data['services'] ?? []);

            $booking = Booking::create([
                'user_id' => $user->id,
                'villa_id' => $villa->id,
                'check_in' => $in,
                'check_out' => $out,
                'guests' => $data['guests'],
                'status' => S::Submitted,
                'total_price' => $quote['total'],
                'deposit_amount' => $quote['deposit'],
            ]);

            foreach ($quote['services'] as $s) {
                $booking->services()->attach($s['id'], ['quantity' => $s['quantity'], 'unit_price' => $s['unit_price']]);
            }

            return $booking;
        });

        BookingSubmitted::dispatch($booking); // Diagramme de séquence : Event -> Listener -> Notifications

        return $booking->refresh()->load(['villa.images', 'user', 'services']);
    }

    public function can(User $u, Booking $b, S $to): bool
    {
        if (! $b->status->canGoTo($to)) {
            return false;
        }
        $isHost = $u->role === 'admin' || $b->villa->owner_id === $u->id;
        $isClient = $b->user_id === $u->id;

        return match ($to) {
            S::Validated, S::InProgress, S::Closed => $isHost,
            S::Cancelled => $isHost || ($isClient && in_array($b->status, [S::Submitted, S::PendingHost, S::Validated], true)),
            default => false,
        };
    }

    public function actionsFor(User $u, Booking $b): array
    {
        $map = ['validate' => S::Validated, 'start' => S::InProgress, 'close' => S::Closed, 'cancel' => S::Cancelled];
        $actions = array_keys(array_filter($map, fn ($to) => $this->can($u, $b, $to)));

        if ($b->user_id === $u->id) {
            if ($b->status === S::Validated) $actions[] = 'pay_deposit';
            if ($b->status === S::DepositPaid) $actions[] = 'pay_balance';
        }

        return $actions;
    }

    public function transition(User $u, Booking $b, string $action): Booking
    {
        $to = ['validate' => S::Validated, 'start' => S::InProgress, 'close' => S::Closed, 'cancel' => S::Cancelled][$action];

        if (! $this->can($u, $b, $to)) {
            abort(403, "Action « {$action} » impossible depuis l'état « {$b->status->value} ».");
        }

        $b->update(['status' => $to]);

        return $b;
    }

    /** Paiement simulé (faux provider) : acompte de 30 % puis solde. */
    public function pay(User $u, Booking $b, string $type): array
    {
        abort_unless($b->user_id === $u->id, 403, 'Réservation non associée à votre compte.');

        [$from, $to, $amount] = $type === 'deposit'
            ? [S::Validated, S::DepositPaid, $b->deposit_amount]
            : [S::DepositPaid, S::BalancePaid, $b->total_price - $b->deposit_amount];

        if ($b->status !== $from) {
            throw ValidationException::withMessages(['type' => "Paiement « {$type} » impossible à ce stade."]);
        }

        $b->update(['status' => $to, 'paid_amount' => $b->paid_amount + $amount]);

        return ['reference' => 'FAKE-'.Str::upper(Str::random(8)), 'amount' => round($amount, 2), 'booking' => $b];
    }
}
