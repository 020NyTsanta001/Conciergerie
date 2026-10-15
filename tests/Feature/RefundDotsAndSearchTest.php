<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundDotsAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role]);
    }

    private function villa(User $host, array $extra = []): Villa
    {
        return Villa::create($extra + [
            'owner_id' => $host->id, 'title' => 'Villa Test', 'description' => 'x', 'city' => 'Nice', 'country' => 'France',
            'price_per_night' => 1000, 'bedrooms' => 3, 'capacity' => 6, 'status' => 'approved',
        ]);
    }

    /** Réservation validée + acompte payé : retourne [host, client, id]. */
    private function depositPaid(): array
    {
        $host = $this->user('host');
        $client = $this->user('client');
        $client->forceFill(['balance' => 5_000_000])->save();
        $villa = $this->villa($host);

        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', [
            'villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'guests' => 2,
        ])->assertCreated()->json('data.id');
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'validate'])->assertOk();
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'deposit'])->assertOk();

        return [$host, $client, $id];
    }

    public function test_host_cannot_book_and_must_use_a_traveler_account(): void
    {
        $host = $this->user('host');
        $villa = $this->villa($host);
        $payload = ['villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-12', 'guests' => 2];

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/bookings/quote', $payload)->assertForbidden();
        $this->actingAs($host, 'sanctum')->postJson('/api/v1/bookings', $payload)->assertForbidden();
    }

    public function test_refund_request_then_host_declines_with_a_cause_shown_to_the_client(): void
    {
        [$host, $client, $id] = $this->depositPaid();

        // la demande exige une cause
        $this->actingAs($client, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'request_refund'])->assertStatus(422);
        $this->actingAs($client, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'request_refund', 'reason' => 'Empêchement familial'])
            ->assertOk()->assertJsonPath('data.refund.status', 'requested');

        // le solde ne peut plus être payé tant que la demande est en attente
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'balance', 'method' => 'wallet'])->assertStatus(422);

        // l'hôte est prévenu (point rouge « Réservations »)
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 3); // demande de réservation + acompte + demande de remboursement

        // le refus exige une cause, qui arrive dans la notification du voyageur
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'decline_refund'])->assertStatus(422);
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'decline_refund', 'reason' => 'Dates trop proches'])
            ->assertOk()->assertJsonPath('data.refund.status', 'declined')->assertJsonPath('data.status', 'deposit_paid');
        $this->assertSame(1, $client->notifications()->where('data', 'like', '%Dates trop proches%')->count());
    }

    public function test_host_accepts_the_refund_without_any_cause(): void
    {
        [$host, $client, $id] = $this->depositPaid();

        // sans demande préalable, l'hôte ne peut pas rembourser
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'refund'])->assertStatus(422);

        $this->actingAs($client, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'request_refund', 'reason' => 'Voyage annulé'])->assertOk();
        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/bookings/{$id}/status", ['action' => 'refund'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refund.status', 'accepted');

        $this->assertSame(5_000_000, (int) $client->fresh()->balance);
        $this->assertSame(0, (int) $host->fresh()->balance);
    }

    public function test_dots_are_counted_per_area_and_cleared_when_read(): void
    {
        [$host, $client, $id] = $this->depositPaid();

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 2); // demande de réservation + acompte
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 1); // réservation acceptée

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/notifications/read', ['area' => 'bookings'])->assertNoContent();
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonMissingPath('bookings');
        $this->actingAs($host, 'sanctum')->postJson('/api/v1/notifications/read', ['area' => 'nimportequoi'])->assertStatus(422);
    }

    public function test_new_villa_and_contact_message_light_the_admin_dots(): void
    {
        $admin = $this->user('admin');
        $host = $this->user('host');

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', [
            'title' => 'Villa Neuve', 'description' => 'Belle villa', 'city' => 'Nosy Be', 'country' => 'Madagascar',
            'price_per_night' => 1000, 'bedrooms' => 2, 'capacity' => 4,
        ])->assertCreated();
        $this->postJson('/api/v1/contact', ['name' => 'Alice', 'email' => 'a@t.test', 'message' => 'Bonjour, une question.'])->assertCreated();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications/dots')
            ->assertJsonPath('moderation', 1)->assertJsonPath('messages', 1);
    }

    public function test_search_finds_the_villa_linked_to_a_yacht(): void
    {
        $host = $this->user('host');
        $this->villa($host, ['title' => 'Villa Azur', 'has_yacht' => true, 'yacht_name' => 'Blue Pearl', 'yacht_places' => ['Nosy Komba']]);
        $this->villa($host, ['title' => 'Villa Sans Bateau']);

        $this->getJson('/api/v1/properties?q=pearl')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Villa Azur');
        $this->getJson('/api/v1/properties?q=komba')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/properties?q=nice')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/properties?has_yacht=true')->assertJsonCount(1, 'data');
    }
}
