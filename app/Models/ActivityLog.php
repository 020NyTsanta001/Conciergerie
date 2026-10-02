<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = [];

    /** Enregistre « Le propriétaire X a ajouté la villa … ». Ignoré hors requête web (seeders, artisan). */
    public static function record(?User $actor, string $action, string $what): void
    {
        if (! $actor || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return;
        }

        $label = ['client' => 'Le voyageur', 'host' => 'Le propriétaire', 'admin' => 'Le concierge'][$actor->role] ?? 'Un utilisateur';

        static::create([
            'user_id' => $actor->exists ? $actor->id : null, // compte supprimé : plus de lien
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'action' => $action,
            'description' => "{$label} {$actor->name} {$what}",
        ]);
    }
}