<?php

namespace App\Http\Controllers;

use App\Http\Resources\VillaResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Villa;
use App\Notifications\InfoNotification;
use App\Services\VillaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HostController extends Controller
{
    public function index(Request $request)
    {
        return VillaResource::collection($request->user()->villas()->with('images')->latest()->get());
    }

    /** Validation commune à la création et à la modification. Retourne [attributs, images|null]. */
    private function validated(Request $request): array
    {
        $yacht = Rule::requiredIf(fn () => $request->boolean('has_yacht'));

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'price_per_night' => 'required|numeric|min:1',
            'bedrooms' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'has_pool' => 'boolean',
            'has_yacht' => 'boolean',
            'yacht_image' => [$yacht, 'nullable', 'url', 'max:500'],
            'yacht_name' => [$yacht, 'nullable', 'string', 'max:80'],
            'yacht_description' => [$yacht, 'nullable', 'string', 'max:300'],
            'yacht_places' => [$yacht, 'nullable', 'array', 'min:1', 'max:2'],
            'yacht_places.*' => ['required', 'string', 'max:80'],
            'images' => 'array|max:10',
            'images.*' => 'url',
        ], [
            'yacht_image.required' => 'Ajoutez le lien de la photo du yacht.',
            'yacht_name.required' => 'Indiquez le nom du yacht.',
            'yacht_description.required' => 'Ajoutez une courte description du yacht.',
            'yacht_places.required' => 'Indiquez au moins un lieu de sortie.',
        ]);

        $attributes = collect($data)->except('images')->all();
        if (empty($attributes['has_yacht'])) {      // pas de yacht : on ne garde aucune information de yacht
            $attributes['has_yacht'] = false;
            foreach (['yacht_image', 'yacht_name', 'yacht_description', 'yacht_places'] as $k) {
                $attributes[$k] = null;
            }
        }

        return [$attributes, $data['images'] ?? null];
    }

    public function store(Request $request)
    {
        [$attributes, $images] = $this->validated($request);

        $villa = $request->user()->villas()->create($attributes + ['status' => 'pending']);
        foreach ($images ?? [] as $i => $url) {
            $villa->images()->create(['url' => $url, 'position' => $i]);
        }

        // point rouge « Modération > Annonces » chez les concierges
        foreach (User::where('role', 'admin')->get() as $admin) {
            $admin->notify(new InfoNotification(
                "Nouvelle villa « {$villa->title} » de {$request->user()->name} : elle attend votre approbation.",
                'moderation'
            ));
        }

        return (new VillaResource($villa->load('images')))->response()->setStatusCode(201);
    }

    /** PUT /host/properties/{villa} : réservé au créateur. Toute modification renvoie la villa en modération. */
    public function update(Request $request, Villa $villa)
    {
        abort_unless((int) $villa->owner_id === (int) $request->user()->id, 403, 'Seul le créateur de la villa peut la modifier.');

        [$attributes, $images] = $this->validated($request);

        $villa->fill($attributes);
        $imagesChanged = $images !== null && $images !== $villa->images()->pluck('url')->all();
        $modified = $villa->isDirty() || $imagesChanged;

        if ($modified) {
            $villa->status = 'pending'; // la villa quitte le site jusqu'à nouvelle approbation
        }
        $villa->save();

        if ($imagesChanged) {
            $villa->images()->delete();
            foreach ($images as $i => $url) {
                $villa->images()->create(['url' => $url, 'position' => $i]);
            }
        }

        if ($modified) {
            ActivityLog::record($request->user(), 'villa_updated', "a modifié la villa « {$villa->title} » (renvoyée en modération)");

            foreach (User::where('role', 'admin')->get() as $admin) {
                $admin->notify(new InfoNotification(
                    "La villa « {$villa->title} » de {$request->user()->name} a été modifiée : elle attend une nouvelle approbation.",
                    'moderation'
                ));
            }
        }

        return new VillaResource($villa->load('images'));
    }

    /** DELETE /host/properties/{villa} : le créateur confirme, sans motif à fournir */
    public function destroy(Request $request, Villa $villa, VillaService $villas)
    {
        abort_unless((int) $villa->owner_id === (int) $request->user()->id, 403, 'Seul le créateur de la villa peut la supprimer ici.');

        $villas->delete($villa, $request->user());

        return response()->noContent();
    }
}
