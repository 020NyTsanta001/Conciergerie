<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function updateName(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $request->user()->update($data);

        return $request->user();
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed', // champ « password_confirmation » attendu
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        abort_if($user->role === 'admin', 403, 'Un compte concierge ne peut pas être supprimé ici.');

        // Garde-fou : pas de suppression tant que des séjours payés ou en cours sont rattachés au compte
        $blocked = Booking::whereIn('status', ['deposit_paid', 'balance_paid', 'in_progress'])
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereHas('villa', fn ($v) => $v->where('owner_id', $user->id)))
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'account' => 'Suppression impossible : des séjours payés ou en cours sont rattachés à votre compte.',
            ]);
        }

        DB::transaction(function () use ($user) {
            // les villas (et leurs photos, réservations, tarifs) disparaissent avec le propriétaire
            $user->villas()->get()->each(function ($villa) {
                $villa->images()->delete();
                $villa->delete();
            });
            $user->images()->delete();
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}