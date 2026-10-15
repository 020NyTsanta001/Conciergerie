<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Villa;
use App\Notifications\InfoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class VillaService
{
    /** Supprime une villa (et ses photos). $reason est fourni quand c'est un admin qui supprime. */
    public function delete(Villa $villa, User $by, ?string $reason = null): void
    {
        $paid = $villa->bookings()->whereIn('status', ['deposit_paid', 'balance_paid', 'in_progress'])->exists();
        if ($paid) {
            throw ValidationException::withMessages([
                'villa' => 'Suppression impossible : des séjours payés ou en cours sont liés à cette villa.',
            ]);
        }

        $villa->loadMissing('owner');
        $title = $villa->title;
        $owner = $villa->owner;

        // voyageurs dont une réservation encore active disparaîtra avec la villa
        $clients = User::whereIn(
            'id',
            $villa->bookings()->whereNotIn('status', ['closed', 'cancelled'])->pluck('user_id')->unique()
        )->get();

        DB::transaction(function () use ($villa, $by, $reason, $title, $owner, $clients) {
            if ((int) $by->id !== (int) $owner->id) { // supprimée par un admin : le propriétaire est prévenu avec la cause
                $owner->notify(new InfoNotification("Votre villa « {$title} » a été supprimée par un concierge. Motif : {$reason}", 'villas'));
            }
            if ($clients->isNotEmpty()) {
                Notification::send($clients, new InfoNotification("La villa « {$title} » a été supprimée : votre réservation est annulée.", 'bookings'));
            }

            $villa->images()->delete();
            $villa->delete();

            ActivityLog::record(
                $by,
                'villa_deleted',
                "a supprimé la villa « {$title} »".((int) $by->id !== (int) $owner->id ? " de {$owner->name} (motif : {$reason})" : '')
            );
        });
    }
}