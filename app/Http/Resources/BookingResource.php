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
            'balance_method' => $this->balance_method,
            'refund' => [
                'status' => $this->refund_status,                       // null | requested | declined | accepted
                'reason' => $this->refund_reason,                       // cause écrite par le voyageur
                'decline_reason' => $this->refund_decline_reason,       // cause écrite par l'hôte s'il refuse
                'requested_at' => $this->refund_requested_at?->toIso8601String(),
            ],
            'review' => $this->whenLoaded('review', fn () => $this->review ? ['id' => $this->review->id, 'rating' => $this->review->rating] : null),
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
