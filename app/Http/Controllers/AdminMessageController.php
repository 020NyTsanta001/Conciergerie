<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;

class AdminMessageController extends Controller
{
    /** GET /admin/messages : messages du formulaire de contact, les plus récents d'abord */
    public function index()
    {
        return ContactMessage::latest()->limit(100)->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'subject' => $m->subject,
            'message' => $m->message,
            'read' => (bool) $m->read_at,
            'created_at' => $m->created_at,
        ]);
    }

    /** PATCH /admin/messages/{message}/read */
    public function read(ContactMessage $message)
    {
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return ['id' => $message->id, 'read' => true];
    }
}
