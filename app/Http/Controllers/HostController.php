<?php

namespace App\Http\Controllers;

use App\Http\Resources\VillaResource;
use App\Models\Villa;
use Illuminate\Http\Request;

class HostController extends Controller
{
    public function index(Request $request)
    {
        return VillaResource::collection($request->user()->villas()->with(['images', 'seasons'])->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'price_per_night' => 'required|numeric|min:1',
            'bedrooms' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'has_pool' => 'boolean',
            'images' => 'array|max:10',
            'images.*' => 'url',
        ]);

        $villa = $request->user()->villas()->create(collect($data)->except('images')->all() + ['status' => 'pending']);
        foreach ($data['images'] ?? [] as $i => $url) {
            $villa->images()->create(['url' => $url, 'position' => $i]);
        }

        return (new VillaResource($villa->load('images')))->response()->setStatusCode(201);
    }

    /** Tarifs saisonniers : PUT /host/properties/{villa}/seasons  [{season_id, price_per_night}] */
    public function syncSeasons(Request $request, Villa $villa)
    {
        abort_unless($villa->owner_id === $request->user()->id, 403);
        $rows = $request->validate([
            'seasons' => 'required|array',
            'seasons.*.season_id' => 'required|exists:seasons,id',
            'seasons.*.price_per_night' => 'required|numeric|min:1',
        ])['seasons'];

        $villa->seasons()->sync(collect($rows)->mapWithKeys(fn ($r) => [$r['season_id'] => ['price_per_night' => $r['price_per_night']]]));

        return new VillaResource($villa->load(['images', 'seasons']));
    }
}
