<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'status' => BookingStatus::class,
            'total_price' => 'float',
            'deposit_amount' => 'float',
            'paid_amount' => 'float',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function villa() { return $this->belongsTo(Villa::class); }

    public function services()
    {
        return $this->belongsToMany(Service::class)->withPivot('quantity', 'unit_price');
    }
}
