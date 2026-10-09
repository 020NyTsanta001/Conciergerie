<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function villa(User $host): Villa
    {
        return Villa::create([
            'owner_id' => $host->id, 'title' => 'Villa Test', 'description' => 'x', 'city' => 'Nice', 'country' => 'France',
            'price_per_night' => 1000, 'bedrooms' => 3, 'capacity' => 6, 'status' => 'approved',
        ]);
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => "{$role}".uniqid().'@t.test', 'password' => 'password', 'role' => $role]);
    }

    public function test_double_booking_is_refused(): void
    {
        $villa = $this->villa($this->user('host'));
        $payload = ['villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-15', 'guests' => 2];

        $this->actingAs($this->user('client'), 'sanctum')->postJson('/api/v1/bookings', $payload)->assertCreated();
        $this->actingAs($this->user('client'), 'sanctum')->postJson('/api/v1/bookings', $payload)->assertStatus(422);
    }

    public function test_full_booking_lifecycle(): void
    {
        $host = $this->user('host');
        $client = $this->user('client');
        $admin = $this->user('admin');
        $villa = $this->villa($host);

        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', [
            'villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'guests' => 2,
        ])->assertCreated()->assertJsonPath('data.status', 'pending_host')->json('data.id');

        // l'admin ne gère pas les réservations ; c'est le propriétaire de la villa qui approuve
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'validate'])->assertForbidden();
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'validate'])->assertJsonPath('data.status', 'validated');

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'deposit'])->assertJsonPath('booking.data.status', 'deposit_paid');
        // l'acompte est notifié à l'hôte seulement
        $this->assertSame(1, $host->notifications()->where('data', 'like', '%Acompte%')->count());
        $this->assertSame(0, $admin->notifications()->where('data', 'like', '%Acompte%')->count());

        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'balance'])->assertJsonPath('booking.data.status', 'balance_paid');

        // payée en intégralité : 2000 € -> 15 % (300 €) pour l'admin, 85 % (1700 €) pour l'hôte au total
        $this->assertDatabaseHas('payouts', [
            'booking_id' => $id, 'host_id' => $host->id,
            'gross' => 2000, 'commission' => 300, 'amount' => 1700, 'status' => 'paid',
        ]);
        $this->assertSame(1, $host->notifications()->where('data', 'like', '%Solde%')->count());
        $this->assertSame(1, $admin->notifications()->where('data', 'like', '%Commission%')->count());

        // l'admin ne démarre pas le séjour ; l'hôte démarre et clôture
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'start'])->assertForbidden();
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'start'])->assertJsonPath('data.status', 'in_progress');
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'close'])->assertJsonPath('data.status', 'closed');
    }

    public function test_client_cannot_validate_own_booking(): void
    {
        $villa = $this->villa($this->user('host'));
        $client = $this->user('client');
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', [
            'villa_id' => $villa->id, 'check_in' => '2027-04-01', 'check_out' => '2027-04-03', 'guests' => 2,
        ])->json('data.id');

        $this->actingAs($client, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'validate'])->assertForbidden();
    }
}
