<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const AREAS = ['moderation', 'commissions', 'messages', 'villas', 'bookings'];

    /** GET /notifications/dots : nombre de notifications non lues par zone, ex. {"bookings":2,"villas":1} */
    public function dots(Request $request)
    {
        $counts = $request->user()->unreadNotifications()->get()
            ->map(fn ($n) => $n->data['area'] ?? null)
            ->filter()
            ->countBy()
            ->all();

        return response()->json((object) $counts);
    }

    /** POST /notifications/read {area} : l'utilisateur a vu cette zone, le point rouge disparaît */
    public function read(Request $request)
    {
        $area = $request->validate(['area' => 'required|in:'.implode(',', self::AREAS)])['area'];

        $request->user()->unreadNotifications()->get()
            ->filter(fn ($n) => ($n->data['area'] ?? null) === $area)
            ->each->markAsRead();

        return response()->noContent();
    }
}
