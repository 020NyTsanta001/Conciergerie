<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class InfoNotification extends Notification
{
    /**
     * $area = zone de l'interface où s'affiche le point rouge (null = pas de point rouge) :
     *   admin  : moderation (annonces) | commissions | messages
     *   host   : villas | bookings
     *   client : bookings
     */
    public function __construct(
        public string $message,
        public ?string $area = null,
        public ?int $bookingId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'booking_id' => $this->bookingId, 'area' => $this->area];
    }
}
