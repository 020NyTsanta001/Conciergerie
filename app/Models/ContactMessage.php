<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'email',
        'message'
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
