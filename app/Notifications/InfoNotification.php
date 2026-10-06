<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class InfoNotification extends Notification
{
    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'booking_id' => null];
    }
}