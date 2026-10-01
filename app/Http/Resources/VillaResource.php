<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VillaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'city' => $this->city,
            'country' => $this->country,
            'price_per_night' => $this->price_per_night,
            'bedrooms' => $this->bedrooms,
            'capacity' => $this->capacity,
            'has_pool' => $this->has_pool,
            'is_featured' => $this->is_featured,
            'status' => $this->status,
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('url')),
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'seasons' => $this->whenLoaded('seasons', fn () => $this->seasons->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name,
                'start_date' => $s->start_date->toDateString(), 'end_date' => $s->end_date->toDateString(),
                'price_per_night' => (float) $s->pivot->price_per_night,
            ])),
        ];
    }
}
