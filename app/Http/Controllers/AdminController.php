<?php

namespace App\Http\Controllers;

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

        return new VillaResource($villa->load(['images', 'owner']));
    }
}
