<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Villa;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;


/** Tarification : prix par nuit de la villa × nombre de nuits + services (au prix fixé par le propriétaire de la villa). */
class PricingService
{
    /** @param array<int, array{id:int, quantity?:int}> $serviceLines */
    public function quote(Villa $villa, Carbon $in, Carbon $out, array $serviceLines = []): array
    {
        // durée maximale d'un séjour (config/booking.php) : refusée avant tout calcul
        $max = (int) config('booking.max_nights');
        if ((int) round(abs($in->diffInDays($out))) > $max) {
            throw ValidationException::withMessages(['check_out' => "Un séjour dure {$max} nuits au maximum."]);
        }

        $nights = [];
        $stay = 0.0;

        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $day) {
            $price = (float) $villa->price_per_night;
            $nights[] = ['date' => $day->toDateString(), 'price' => $price];
            $stay += $price;
        }

        $quantities = collect($serviceLines)->pluck('quantity', 'id');
        $services = Service::whereIn('id', $quantities->keys())->where('is_active', true)->get()
            ->map(function ($s) use ($villa, $quantities) {
                $price = $villa->servicePrice($s->type); // prix du propriétaire, et non plus celui du catalogue
                if ($price === null) {
                    throw ValidationException::withMessages(['services' => "« {$s->name} » n'est pas proposé pour cette villa."]);
                }

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'quantity' => max(1, (int) ($quantities[$s->id] ?? 1)),
                    'unit_price' => (float) $price,
                ];
            });
        $servicesTotal = $services->sum(fn ($s) => $s['quantity'] * $s['unit_price']);

        $total = Money::round($stay + $servicesTotal);

        return [
            'nights' => $nights,
            'nights_count' => count($nights),
            'stay_total' => Money::round($stay),
            'services' => $services->values()->all(),
            'services_total' => Money::round($servicesTotal),
            'total' => $total,
            'deposit' => Money::deposit($total),
        ];
    }
}
