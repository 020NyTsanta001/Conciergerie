<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Villa extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_pool' => 'boolean', 'has_yacht' => 'boolean', 'is_featured' => 'boolean', 'price_per_night' => 'float', 'yacht_places' => 'array'];
    }

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }

    public function images() { return $this->morphMany(Image::class, 'imageable')->orderBy('position'); }

    public function bookings() { return $this->hasMany(Booking::class); }

    public function scopeApproved(Builder $q): Builder { return $q->where('status', 'approved'); }

    /** Filtrage avancé : GET /api/v1/properties?price_min=500&has_pool=true&city=Nice ... */
    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['city'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(city) LIKE ?', ['%'.mb_strtolower($v).'%']))
            ->when($f['price_min'] ?? null, fn ($q, $v) => $q->where('price_per_night', '>=', $v))
            ->when($f['price_max'] ?? null, fn ($q, $v) => $q->where('price_per_night', '<=', $v))
            ->when($f['guests'] ?? null, fn ($q, $v) => $q->where('capacity', '>=', $v))
            ->when(filter_var($f['has_pool'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->where('has_pool', true))
            ->when(filter_var($f['featured'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->where('is_featured', true))
            ->when(filter_var($f['has_yacht'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->where('has_yacht', true))
            // recherche libre : la villa OU son yacht (nom, description, lieux de sortie) correspond
            ->when(trim((string) ($f['q'] ?? '')) !== '', function ($q) use ($f) {
                $like = '%'.mb_strtolower(trim($f['q'])).'%';
                $text = DB::connection()->getDriverName() === 'mysql' ? 'CHAR' : 'TEXT'; // yacht_places est une colonne JSON

                $q->where(function ($w) use ($like, $text) {
                    foreach (['title', 'city', 'country', 'description', 'yacht_name', 'yacht_description'] as $col) {
                        $w->orWhereRaw("LOWER({$col}) LIKE ?", [$like]);
                    }
                    $w->orWhereRaw("LOWER(CAST(yacht_places AS {$text})) LIKE ?", [$like]);
                });
            });
    }
    public function reviews() { return $this->hasMany(Review::class); }
}
