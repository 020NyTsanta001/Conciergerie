<?php

namespace App\Http\Resources;

use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'guests' => $this->guests,
            'total_price' => $this->total_price,
            'deposit_amount' => $this->deposit_amount,
            'paid_amount' => $this->paid_amount,
            'villa' => new VillaResource($this->whenLoaded('villa')),
            'client' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name,
                'quantity' => $s->pivot->quantity, 'unit_price' => (float) $s->pivot->unit_price,
            ])),
            'actions' => app(BookingService::class)->actionsFor($request->user(), $this->resource),
        ];
    }
}
