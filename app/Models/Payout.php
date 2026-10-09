<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['gross' => 'float', 'commission' => 'float', 'amount' => 'float', 'paid_at' => 'datetime'];
    }

    public function booking() { return $this->belongsTo(Booking::class); }

    public function host() { return $this->belongsTo(User::class, 'host_id'); }
}