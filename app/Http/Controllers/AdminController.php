<?php

namespace App\Http\Controllers;
use App\Notifications\InfoNotification;
use App\Services\VillaService;

use App\Http\Resources\VillaResource;
use App\Models\Villa;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function properties(Request $request)
    {
        $status = $request->query('status', 'pending');

        return VillaResource::collection(Villa::with(['images', 'owner'])->where('status', $status)->latest()->get());
    }

    public function moderate(Request $request, Villa $villa)
    {
        $villa->update($request->validate(['status' => 'required|in:approved,rejected']));

        // point rouge « Mes villas » chez le propriétaire : villa acceptée ou refusée
        if ($villa->wasChanged('status')) {
            $villa->loadMissing('owner');
            $villa->owner->notify(new InfoNotification(
                $villa->status === 'approved'
                    ? "Votre villa « {$villa->title} » a été acceptée : elle est maintenant visible sur le site."
                    : "Votre villa « {$villa->title} » a été refusée par un concierge. Vous pouvez la modifier et la soumettre à nouveau.",
                'villas'
            ));
        }

        return new VillaResource($villa->load(['images', 'owner']));
    }
        /** DELETE /admin/properties/{villa}  {reason} : l'admin doit écrire la cause */
    public function destroy(Request $request, Villa $villa, VillaService $villas)
    {
        $reason = $request->validate(
            ['reason' => 'required|string|min:5|max:500'],
            ['reason.required' => 'Indiquez la cause de la suppression.', 'reason.min' => 'La cause est trop courte (5 caractères minimum).']
        )['reason'];

        $villas->delete($villa, $request->user(), $reason);

        return response()->noContent();
    }
}
