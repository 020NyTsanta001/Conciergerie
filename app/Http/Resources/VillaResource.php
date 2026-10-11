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
            'has_yacht' => $this->has_yacht,
            'yacht_image' => $this->yacht_image,
            'yacht_name' => $this->yacht_name,
            'yacht_description' => $this->yacht_description,
            'yacht_places' => $this->yacht_places,

            'is_featured' => $this->is_featured,
            'status' => $this->status,
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('url')),
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
        ];
    }
}
