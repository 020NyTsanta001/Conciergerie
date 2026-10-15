<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;

class BookingSubmittedNotification extends Notification
{
    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database']; // évolutif : 'mail', 'broadcast' (WebSockets)
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            // point rouge « Réservations » pour le propriétaire seulement (les concierges ne gèrent pas les réservations)
            'area' => ($notifiable->role ?? null) === 'host' ? 'bookings' : null,
            'message' => "Nouvelle demande de {$this->booking->user->name} pour « {$this->booking->villa->title} » ".
                "du {$this->booking->check_in->format('d/m/Y')} au {$this->booking->check_out->format('d/m/Y')}.",
        ];
    }
}
