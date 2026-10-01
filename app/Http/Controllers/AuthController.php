<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,host', // l'admin ne s'inscrit pas
            'phone' => 'nullable|string|max:30',
        ]);

        $user = User::create($data);

        return response()->json(['user' => $user, 'token' => $user->createToken('spa')->plainTextToken], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }

        $user = User::where('email', $credentials['email'])->firstOrFail();

        return ['user' => $user, 'token' => $user->createToken('spa')->plainTextToken];
    }

    public function me(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
