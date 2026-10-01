<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:120',
            'email'=>'required|email|max:190',
            'subject'=>'nullable|string|max:150',
            'message'=>'required|string|min:10|max:2000',
        ]);

        ContactMessage::create($data);
    return response()->json(['message'=>'Merci, votre message a bien été envoyé'], 201);
    }
    
}
