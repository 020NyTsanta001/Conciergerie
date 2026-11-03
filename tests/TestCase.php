<?php

namespace Tests;

use App\Models\Service;
use App\Models\User;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

/**
 * Base commune des tests Hoxen.
 * Montants en Ariary (entiers). Prix de référence : villa 100 000 Ar la nuit.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Garde-fou : RefreshDatabase vide la base ; on refuse de tourner ailleurs que sur une base « *_test ». */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_test')) {
            throw new \RuntimeException("Tests refusés : la base « {$database} » n'est pas une base de test (nom attendu : *_test).");
        }
    }

    /* ------------------------------ fabriques ------------------------------ */

    protected function makeUser(string $role = 'client', array $extra = []): User
    {
        return User::create($extra + [
            'name' => ucfirst($role).' Test',
            'email' => $role.'.'.Str::lower(Str::random(8)).'@test.local',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    protected function fund(User $user, int $amount): User
    {
        $user->forceFill(['balance' => $amount])->save();

        return $user;
    }

    protected function makeVilla(User $host, array $extra = []): Villa
    {
        return Villa::create($extra + [
            'owner_id' => $host->id,
            'title' => 'Villa '.Str::random(6),
            'description' => 'Villa de test',
            'city' => 'Nosy Be',
            'country' => 'Madagascar',
            'price_per_night' => 100000,
            'bedrooms' => 3,
            'capacity' => 6,
            'status' => 'approved',
        ]);
    }

    protected function makeYachtVilla(User $host, array $extra = []): Villa
    {
        return $this->makeVilla($host, $extra + [
            'has_yacht' => true,
            'yacht_image' => 'https://img.test/yacht.jpg',
            'yacht_name' => 'Yacht Test',
            'yacht_description' => 'Sortie en mer de test',
            'yacht_places' => ['Nosy Komba'],
            'yacht_price' => 400000,
        ]);
    }

    /** Catalogue de services (chef, chauffeur, yacht), indexé par type. */
    protected function seedServices(): array
    {
        foreach (['chef' => 'Chef à domicile', 'chauffeur' => 'Chauffeur privé', 'yacht' => 'Sortie en yacht'] as $type => $name) {
            Service::firstOrCreate(['type' => $type], ['name' => $name, 'price' => 1, 'is_active' => true]);
        }

        return Service::all()->keyBy('type')->all();
    }

    /* ------------------------------- helpers ------------------------------- */

    /** Date (Y-m-d) à $offset jours d'aujourd'hui : évite toute date figée qui deviendrait passée. */
    protected function day(int $offset): string
    {
        return Carbon::today()->addDays($offset)->toDateString();
    }

    protected function bookingPayload(Villa $villa, int $fromDays = 10, int $nights = 2, array $extra = []): array
    {
        return $extra + [
            'villa_id' => $villa->id,
            'check_in' => $this->day($fromDays),
            'check_out' => $this->day($fromDays + $nights),
            'guests' => 2,
        ];
    }

    protected function status(User $actor, int $bookingId, string $action, array $extra = [])
    {
        return $this->actingAs($actor, 'sanctum')->patchJson("/api/v1/bookings/{$bookingId}/status", ['action' => $action] + $extra);
    }

    protected function pay(User $client, int $bookingId, string $type, string $method = 'wallet')
    {
        return $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$bookingId}/pay", ['type' => $type, 'method' => $method]);
    }

    /**
     * Réservation validée dont l'acompte est payé (villa à 100 000 Ar, 2 nuits : total 200 000, acompte 60 000).
     * Retourne ['admin', 'host', 'client', 'villa', 'id'].
     */
    protected function depositPaid(int $fromDays = 10, int $nights = 2, array $villaExtra = []): array
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $villa = $this->makeVilla($host, $villaExtra);

        $id = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/bookings', $this->bookingPayload($villa, $fromDays, $nights))
            ->assertCreated()->json('data.id');

        $this->status($host, $id, 'validate')->assertOk();
        $this->pay($client, $id, 'deposit')->assertOk();

        return compact('admin', 'host', 'client', 'villa', 'id');
    }
}
