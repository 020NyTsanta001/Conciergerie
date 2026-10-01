<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Villa;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/** Tarification dynamique : prix par nuit selon la saison (pivot villa_season) + services. */
class PricingService
{
    public const DEPOSIT_RATE = 0.30;

    /** @param array<int, array{id:int, quantity?:int}> $serviceLines */
    public function quote(Villa $villa, Carbon $in, Carbon $out, array $serviceLines = []): array
    {
        $seasons = $villa->seasons;
        $nights = [];
        $stay = 0.0;

        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $day) {
            $season = $seasons->first(fn ($s) => $day->between($s->start_date, $s->end_date));
            $price = $season ? (float) $season->pivot->price_per_night : (float) $villa->price_per_night;
            $nights[] = ['date' => $day->toDateString(), 'price' => $price, 'season' => $season?->name];
            $stay += $price;
        }

        $quantities = collect($serviceLines)->pluck('quantity', 'id');
        $services = Service::whereIn('id', $quantities->keys())->where('is_active', true)->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'quantity' => max(1, (int) ($quantities[$s->id] ?? 1)),
                'unit_price' => (float) $s->price,
            ]);
        $servicesTotal = $services->sum(fn ($s) => $s['quantity'] * $s['unit_price']);

        $total = round($stay + $servicesTotal, 2);

        return [
            'nights' => $nights,
            'nights_count' => count($nights),
            'stay_total' => round($stay, 2),
            'services' => $services->values()->all(),
            'services_total' => round($servicesTotal, 2),
            'total' => $total,
            'deposit' => round($total * self::DEPOSIT_RATE, 2),
        ];
    }
}
