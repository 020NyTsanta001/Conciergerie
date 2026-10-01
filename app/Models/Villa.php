<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Villa extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_pool' => 'boolean', 'is_featured' => 'boolean', 'price_per_night' => 'float'];
    }

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }

    public function images() { return $this->morphMany(Image::class, 'imageable')->orderBy('position'); }

    public function seasons() { return $this->belongsToMany(Season::class, 'villa_season')->withPivot('price_per_night'); }

    public function bookings() { return $this->hasMany(Booking::class); }

    public function scopeApproved(Builder $q): Builder { return $q->where('status', 'approved'); }

    /** Filtrage avancé : GET /api/v1/properties?price_min=500&has_pool=true&city=Nice ... */
    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['city'] ?? null, fn ($q, $v) => $q->where('city', 'like', "%{$v}%"))
            ->when($f['price_min'] ?? null, fn ($q, $v) => $q->where('price_per_night', '>=', $v))
            ->when($f['price_max'] ?? null, fn ($q, $v) => $q->where('price_per_night', '<=', $v))
            ->when($f['guests'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v))
            ->when(filter_var($f['has_pool'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->where('has_pool', true))
            ->when(filter_var($f['featured'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->where('is_featured', true))
            ->when(($f['available_from'] ?? null) && ($f['available_to'] ?? null), function ($q) use ($f) {
                $q->whereDoesntHave('bookings', fn ($b) => $b
                    ->where('status', '!=', 'cancelled')
                    ->where('check_in', '<', $f['available_to'])
                    ->where('check_out', '>', $f['available_from']));
            });
    }
}
