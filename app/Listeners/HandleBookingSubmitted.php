<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingSubmitted;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use Illuminate\Support\Facades\Notification;

// Auto-découvert par Laravel (type-hint de handle()). Ajouter ShouldQueue pour l'exécuter en tâche de fond.
class HandleBookingSubmitted
{
    public function handle(BookingSubmitted $event): void
    {
        $booking = $event->booking->load('villa.owner', 'user');
        $booking->update(['status' => BookingStatus::PendingHost]);

        $recipients = User::where('role', 'admin')->get()->push($booking->villa->owner)->unique('id');
        Notification::send($recipients, new BookingSubmittedNotification($booking));
    }
}
