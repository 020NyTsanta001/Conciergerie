<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Villa;

class StatsController extends Controller
{
    public function __invoke()
    {
        return [
            'properties' => Villa::approved()->count(),
            'bookings' => Booking::count(),
            'years_experience' => (int) config('app.years_experience', 15),
        ];
    }
}
