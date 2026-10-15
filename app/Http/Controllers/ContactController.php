<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\InfoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

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

        $contact = ContactMessage::create($data);

        // point rouge sur « Modération > Messages » chez les concierges
        Notification::send(
            User::where('role', 'admin')->get(),
            new InfoNotification(
                "Nouveau message de {$contact->name}".($contact->subject ? " : « {$contact->subject} »" : '').'.',
                'messages'
            )
        );

        return response()->json(['message'=>'Merci, votre message a bien été envoyé'], 201);
    }
}
