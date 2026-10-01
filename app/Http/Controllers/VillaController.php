<?php

namespace App\Http\Controllers;

use App\Http\Resources\VillaResource;
use App\Models\Season;
use App\Models\Villa;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function index(Request $request)
    {
        // Mode « sélection aléatoire » : GET /properties?random=5
        if ($request->filled('random')) {
            $n = min(max((int) $request->query('random'), 1), 12);

            return VillaResource::collection(
                Villa::approved()
                    ->whereHas('images')          // uniquement des villas avec au moins une photo
                    ->with('images')
                    ->filter($request->query())
                    ->inRandomOrder()
                    ->limit($n)
                    ->get()
            );
        }


        // Eager loading : évite le problème N+1 (une requête pour les images de toute la page)
        $villas = Villa::approved()->with('images')
            ->filter($request->query())
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return VillaResource::collection($villas);
    }

    public function show(Villa $villa)
    {
        abort_unless($villa->status === 'approved', 404);

        return new VillaResource($villa->load(['images', 'owner', 'seasons']));
    }

    public function seasons()
    {
        return Season::orderBy('start_date')->get();
    }
}
