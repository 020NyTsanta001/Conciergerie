<?php

namespace App\Services;

use App\Enums\BookingStatus as S;
use App\Events\BookingSubmitted;
use App\Models\Booking;
use App\Models\Payout;
use App\Models\User;
use App\Models\Villa;
use App\Models\ActivityLog;
use App\Notifications\InfoNotification;
use App\Payments\PaymentGateway;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private PricingService $pricing,
        private PaymentGateway $gateway,  // encaissement du voyageur : simulé aujourd'hui, prestataire réel demain
        private WalletService $wallet,    // soldes internes : hôte et plateforme
    ) {}

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

        BookingSubmitted::dispatch($booking); // Event -> Listener -> Notifications

        return $booking->refresh()->load(['villa.images', 'user', 'services']);
    }

    /* ------------------------------------------------------------------ */
    /*  Droits : qui peut faire quoi                                       */
    /* ------------------------------------------------------------------ */

    public function can(User $u, Booking $b, S $to): bool
    {
        if (! $b->status->canGoTo($to)) {
            return false;
        }
        // L'admin ne gère aucune réservation : il les consulte seulement.
        $isOwner = $b->villa->owner_id === $u->id;
        $isClient = $b->user_id === $u->id;
        $beforeAnyPayment = in_array($b->status, [S::Submitted, S::PendingHost, S::Validated], true);

        return match ($to) {
            S::Validated, S::InProgress, S::Closed => $isOwner,
            S::BalancePaid => $isOwner && $b->balance_method === 'on_site', // validation du paiement reçu sur place
            // annulation simple : seulement tant qu'aucun argent n'est versé (après l'acompte : remboursement par l'hôte)
            S::Cancelled => $beforeAnyPayment && ($isOwner || $isClient),
            default => false,
        };
    }

    /** Remboursement de l'acompte : réservé à l'hôte, avant la date de séjour, tant que le solde n'est pas payé. */
    public function canRefund(User $u, Booking $b): bool
    {
        return $b->status === S::DepositPaid
            && $b->villa->owner_id === $u->id
            && Carbon::today()->lt($b->check_in);   // la date du séjour atteinte = plus de remboursement
    }

    public function actionsFor(User $u, Booking $b): array
    {
        $map = [
            'validate' => S::Validated, 'confirm_onsite' => S::BalancePaid,
            'start' => S::InProgress, 'close' => S::Closed, 'cancel' => S::Cancelled,
        ];
        $actions = array_keys(array_filter($map, fn ($to) => $this->can($u, $b, $to)));

        if ($this->canRefund($u, $b)) {
            $actions[] = 'refund';
        }
        if ($b->user_id === $u->id) {
            if ($b->status === S::Validated) $actions[] = 'pay_deposit';
            if ($b->status === S::DepositPaid) $actions[] = 'pay_balance';
        }

        return $actions;
    }

    public function transition(User $u, Booking $b, string $action): Booking
    {
        if ($action === 'refund') {
            return $this->refundDeposit($u, $b);
        }

        $to = [
            'validate' => S::Validated, 'confirm_onsite' => S::BalancePaid,
            'start' => S::InProgress, 'close' => S::Closed, 'cancel' => S::Cancelled,
        ][$action];

        if (! $this->can($u, $b, $to)) {
            abort(403, "Action « {$action} » impossible depuis l'état « {$b->status->value} ».");
        }

        if ($action === 'confirm_onsite') {
            $this->settleOnSite($b);

            return $b;
        }

        $b->update(['status' => $to]);
        if ($to === S::Closed) {
            $b->loadMissing('user', 'villa');
            $b->user->notify(new InfoNotification(
                "Votre séjour à « {$b->villa->title} » est terminé : vous pouvez laisser un avis sur la page de la villa."
            ));
        }

        return $b;
    }

    /** L'hôte rembourse l'acompte (jamais la totalité) avant la date de séjour : la réservation est annulée et les dates sont libérées. */
    private function refundDeposit(User $u, Booking $b): Booking
    {
        $b->loadMissing('villa.owner', 'user');

        abort_unless($b->villa->owner_id === $u->id, 403, "Seul le propriétaire de la villa peut rembourser l'acompte.");

        if ($b->status !== S::DepositPaid) {
            throw ValidationException::withMessages(['refund' => "Aucun acompte remboursable : le remboursement ne couvre que l'acompte, tant que le solde n'est pas payé."]);
        }
        if (! Carbon::today()->lt($b->check_in)) {
            throw ValidationException::withMessages(['refund' => "Remboursement impossible : la date du séjour est atteinte. L'acompte reste acquis et la villa reste indisponible pour ces dates."]);
        }

        $amount = (int) $b->deposit_amount;

        DB::transaction(function () use ($u, $b, $amount) {
            // l'hôte rend l'acompte qu'il avait reçu ; le voyageur le récupère
            $this->wallet->debit($u, $amount, 'refund', "Remboursement acompte réservation #{$b->id}", $b->id,
                "Votre solde est insuffisant pour rembourser l'acompte (".Money::format($amount).').');
            $this->gateway->refund($b->user, $amount, "Remboursement acompte réservation #{$b->id}", $b->id);

            $b->update(['status' => S::Cancelled, 'paid_amount' => max(0, $b->paid_amount - $amount)]);

            $b->user->notify(new InfoNotification(
                "Votre acompte de ".Money::format($amount)." pour la réservation #{$b->id} (« {$b->villa->title} ») a été remboursé par le propriétaire. La réservation est annulée."
            ));
        });

        ActivityLog::record($u, 'booking_refund', "a remboursé l'acompte de ".Money::format($amount)." (réservation #{$b->id})");

        return $b;
    }

    /* ------------------------------------------------------------------ */
    /*  Paiements                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * $type   : 'deposit' (acompte) ou 'balance' (solde du séjour)
     * $method : 'wallet' (mon solde) ou 'on_site' (solde payé sur place, seulement pour 'balance')
     */
    public function pay(User $u, Booking $b, string $type, string $method = 'wallet'): array
    {
        abort_unless($b->user_id === $u->id, 403, 'Réservation non associée à votre compte.');
        $b->loadMissing('villa.owner');

        if ($type === 'deposit') {
            if ($method !== 'wallet') {
                throw ValidationException::withMessages(['method' => "L'acompte se paie depuis votre solde."]);
            }
            $this->requireStatus($b, S::Validated, 'acompte');

            return DB::transaction(function () use ($u, $b) {
                $amount = (int) $b->deposit_amount;
                $payment = $this->gateway->charge($u, $amount, "Acompte réservation #{$b->id}", $b->id);

                // l'acompte va à l'hôte, et seul lui est notifié
                $this->wallet->credit($b->villa->owner, $amount, 'receipt', "Acompte réservation #{$b->id}", $b->id);
                $b->update(['status' => S::DepositPaid, 'paid_amount' => $b->paid_amount + $amount]);
                $b->villa->owner->notify(new InfoNotification(
                    "Acompte reçu pour la réservation #{$b->id} (« {$b->villa->title} ») : ".Money::format($amount).'.'
                ));

                return ['reference' => $payment->reference, 'amount' => $amount, 'booking' => $b];
            });
        }

        $this->requireStatus($b, S::DepositPaid, 'solde');
        $balance = (int) ($b->total_price - $b->deposit_amount);

        if ($method === 'on_site') {
            // paiement remis à l'hôte en main propre : rien n'est débité ; l'hôte valide à l'arrivée
            $b->update(['balance_method' => 'on_site']);
            $b->villa->owner->notify(new InfoNotification(
                "Le voyageur paiera le solde de ".Money::format($balance)." sur place au début du séjour (réservation #{$b->id}). Validez la réception à son arrivée."
            ));

            return ['reference' => 'ON-SITE', 'amount' => 0, 'booking' => $b];
        }

        return DB::transaction(function () use ($u, $b, $balance) {
            $payment = $this->gateway->charge($u, $balance, "Solde réservation #{$b->id}", $b->id);
            $b->update(['status' => S::BalancePaid, 'paid_amount' => $b->total_price, 'balance_method' => 'wallet']);
            $this->settleWallet($b);

            return ['reference' => $payment->reference, 'amount' => $balance, 'booking' => $b];
        });
    }

    private function requireStatus(Booking $b, S $expected, string $label): void
    {
        if ($b->status !== $expected) {
            throw ValidationException::withMessages(['type' => "Paiement « {$label} » impossible à ce stade."]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Répartition de l'argent d'un séjour payé en intégralité            */
    /* ------------------------------------------------------------------ */

    /** [total, commission plateforme, part de l'hôte] */
    private function split(Booking $b): array
    {
        $gross = (int) $b->total_price;
        $commission = Money::commission($gross);

        return [$gross, $commission, $gross - $commission];
    }

    /** Solde payé depuis le compte du voyageur : l'hôte reçoit sa part (acompte déjà reçu), l'admin sa commission. */
    private function settleWallet(Booking $b): void
    {
        [$gross, $commission, $hostShare] = $this->split($b);
        $host = $b->villa->owner;
        $hostBalance = $hostShare - (int) $b->deposit_amount;
        $pct = (int) round((1 - config('money.commission_rate')) * 100);

        $this->wallet->credit($host, $hostBalance, 'receipt', "Solde réservation #{$b->id}", $b->id);
        $this->creditPlatform($b, $host, $commission);
        $this->recordPayout($b, $host, $gross, $commission, $hostShare);

        $host->notify(new InfoNotification(
            "Solde reçu pour la réservation #{$b->id} (« {$b->villa->title} ») : ".Money::format($hostBalance).
            '. Total reçu avec l\'acompte : '.Money::format($hostShare)." ({$pct} %)."
        ));
    }

    /** Solde payé sur place : le voyageur n'est pas débité ; la commission est prélevée sur le solde de l'hôte. */
    private function settleOnSite(Booking $b): void
    {
        $b->loadMissing('villa.owner');
        [$gross, $commission, $hostShare] = $this->split($b);
        $host = $b->villa->owner;

        DB::transaction(function () use ($b, $host, $gross, $commission, $hostShare) {
            $this->wallet->debit($host, $commission, 'commission', "Commission réservation #{$b->id} (solde payé sur place)", $b->id,
                'Solde insuffisant pour régler la commission de la plateforme ('.Money::format($commission).').');

            $b->update(['status' => S::BalancePaid, 'paid_amount' => $gross]);
            $this->creditPlatform($b, $host, $commission);
            $this->recordPayout($b, $host, $gross, $commission, $hostShare);

            $host->notify(new InfoNotification(
                "Paiement sur place validé pour la réservation #{$b->id} : commission de ".Money::format($commission).' prélevée sur votre solde.'
            ));
        });
    }

    private function creditPlatform(Booking $b, User $host, int $commission): void
    {
        $admins = User::where('role', 'admin')->orderBy('id')->get();
        if ($admins->isEmpty()) {
            return;
        }

        // la commission est créditée au premier admin (compte de la plateforme) ; tous sont notifiés
        $this->wallet->credit($admins->first(), $commission, 'commission',
            "Commission villa « {$b->villa->title} » par {$host->name}", $b->id);

        foreach ($admins as $admin) {
            $admin->notify(new InfoNotification(
                "Commission de la villa « {$b->villa->title} » par {$host->name} : ".Money::format($commission)." (réservation #{$b->id})."
            ));
        }
    }

    private function recordPayout(Booking $b, User $host, int $gross, int $commission, int $hostShare): void
    {
        Payout::firstOrCreate(['booking_id' => $b->id], [
            'host_id' => $host->id, 'gross' => $gross, 'commission' => $commission, 'amount' => $hostShare,
            'status' => 'paid', 'paid_at' => now(),
        ]);
    }
}