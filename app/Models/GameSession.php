<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'paid' => 'boolean'];
    }
}