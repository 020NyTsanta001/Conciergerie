<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use App\Models\Villa;

class ActivityTracker
{
    public static function register(): void
    {
        User::created(fn (User $u) => ActivityLog::record($u, 'register', "s'est inscrit"));

        User::updated(function (User $u) {
            if ($u->wasChanged('name')) {
                ActivityLog::record($u, 'name_changed', "a changé son nom (« {$u->getOriginal('name')} » → « {$u->name} »)");
            }
            if ($u->wasChanged('password')) {
                ActivityLog::record($u, 'password_changed', 'a changé son mot de passe');
            }
            if ($u->wasChanged('suspended_at')) {
                ActivityLog::record(
                    request()->user(), // l'admin qui agit
                    $u->suspended_at ? 'account_suspended' : 'account_reactivated',
                    $u->suspended_at ? "a suspendu le compte de {$u->name}" : "a réactivé le compte de {$u->name}"
                );
            }
        });

        User::deleted(fn (User $u) => ActivityLog::record($u, 'account_deleted', 'a supprimé son compte'));

        Villa::created(fn (Villa $v) => ActivityLog::record(request()->user(), 'villa_created', "a ajouté la villa « {$v->title} »"));

        Villa::updated(function (Villa $v) {
            if ($v->wasChanged('status') && in_array($v->status, ['approved', 'rejected'], true)) {
                $verb = $v->status === 'approved' ? 'approuvé' : 'rejeté';
                ActivityLog::record(request()->user(), 'villa_'.$v->status, "a {$verb} la villa « {$v->title} » de {$v->owner->name}");
            }
        });

        Booking::created(fn (Booking $b) => ActivityLog::record(
            request()->user(),
            'booking_created',
            "a réservé « {$b->villa->title} » du {$b->check_in->format('d/m/Y')} au {$b->check_out->format('d/m/Y')}"
        ));

        Booking::updated(function (Booking $b) {
            if (! $b->wasChanged('status')) {
                return;
            }
            $what = match ($b->status) {
                BookingStatus::Validated => "a validé la réservation #{$b->id}",
                BookingStatus::DepositPaid => "a payé l'acompte de la réservation #{$b->id}",
                BookingStatus::BalancePaid => "a payé le solde de la réservation #{$b->id}",
                BookingStatus::InProgress => "a démarré le séjour de la réservation #{$b->id}",
                BookingStatus::Closed => "a clôturé la réservation #{$b->id}",
                BookingStatus::Cancelled => "a annulé la réservation #{$b->id}",
                default => null, // la transition automatique « soumise → en attente » n'est pas une action humaine
            };
            if ($what) {
                ActivityLog::record(request()->user(), 'booking_'.$b->status->value, "{$what} (« {$b->villa->title} »)");
            }
        });
    }
}