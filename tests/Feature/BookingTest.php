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
        $villa = $this->villa($host);

        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', [
            'villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'guests' => 2,
        ])->assertCreated()->assertJsonPath('data.status', 'pending_host')->json('data.id');

        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'validate'])->assertJsonPath('data.status', 'validated');
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'deposit'])->assertJsonPath('booking.data.status', 'deposit_paid');
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'balance'])->assertJsonPath('booking.data.status', 'balance_paid');
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
