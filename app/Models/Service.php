<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'float'];
    }

    public function images() { return $this->morphMany(Image::class, 'imageable'); }
}
