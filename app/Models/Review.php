<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'user_id' => 'integer', 'villa_id' => 'integer', 'booking_id' => 'integer'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function villa() { return $this->belongsTo(Villa::class); }

    public function booking() { return $this->belongsTo(Booking::class); }
}