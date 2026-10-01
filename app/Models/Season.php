<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function villas() { return $this->belongsToMany(Villa::class, 'villa_season')->withPivot('price_per_night'); }
}
