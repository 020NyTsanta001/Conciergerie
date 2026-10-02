<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /** GET /admin/users?role=client|host&search=... */
    public function index(Request $request)
    {
        $request->validate(['role' => 'nullable|in:client,host']);

        return User::whereIn('role', $request->filled('role') ? [$request->query('role')] : ['client', 'host'])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->withCount(['villas', 'bookings'])
            ->latest()
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role,
                'created_at' => $u->created_at,
                'suspended' => (bool) $u->suspended_at,
                'villas_count' => $u->villas_count,
                'bookings_count' => $u->bookings_count,
            ]);
    }

    /** PATCH /admin/users/{user}/suspend  {suspended: true|false} */
    public function suspend(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403, 'Un compte concierge ne peut pas être suspendu.');
        $suspended = $request->validate(['suspended' => 'required|boolean'])['suspended'];

        $user->update(['suspended_at' => $suspended ? now() : null]);
        if ($suspended) {
            $user->tokens()->delete(); // déconnecte l'utilisateur immédiatement
        }

        return ['id' => $user->id, 'suspended' => $suspended];
    }
}