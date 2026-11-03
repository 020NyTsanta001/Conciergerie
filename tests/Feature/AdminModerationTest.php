<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    public function test_admin_lists_pending_villas_by_default_and_other_statuses_on_demand(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $this->makeVilla($host, ['status' => 'pending']);
        $this->makeVilla($host, ['status' => 'approved']);
        $this->makeVilla($host, ['status' => 'approved']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/properties')->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/properties?status=approved')->assertJsonCount(2, 'data');
    }

    public function test_approving_publishes_the_villa_and_tells_the_owner_once(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host, ['status' => 'pending']);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'approved'])->assertOk();

        $this->getJson("/api/v1/properties/{$villa->id}")->assertOk();
        $this->assertSame(1, $host->notifications()->count(), 'une seule notification : le statut n\'a changé qu\'une fois');
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('villas', 1);
    }

    public function test_rejecting_hides_the_villa_and_tells_the_owner(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host, ['status' => 'pending']);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'rejected'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->getJson("/api/v1/properties/{$villa->id}")->assertNotFound();
        $this->assertSame(1, $host->notifications()->where('data', 'like', '%refus%')->count());
    }

    public function test_moderation_accepts_only_approved_or_rejected(): void
    {
        $admin = $this->makeUser('admin');
        $villa = $this->makeVilla($this->makeUser('host'), ['status' => 'pending']);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'pending'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", [])->assertStatus(422);
    }

    public function test_only_the_admin_moderates(): void
    {
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($host, ['status' => 'pending']);

        $this->actingAs($host, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'approved'])->assertForbidden();
        $this->actingAs($client, 'sanctum')->patchJson("/api/v1/admin/properties/{$villa->id}/moderate", ['status' => 'approved'])->assertForbidden();
    }

    public function test_admin_deletion_needs_a_reason_and_the_owner_receives_it(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/properties/{$villa->id}")->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/properties/{$villa->id}", ['reason' => 'abc'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/properties/{$villa->id}", ['reason' => 'Photos non conformes'])->assertNoContent();

        $this->assertDatabaseMissing('villas', ['id' => $villa->id]);
        $this->assertSame(1, $host->notifications()->where('data', 'like', '%Photos non conformes%')->count());
    }

    public function test_deleting_a_villa_warns_travellers_with_an_active_booking(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($host);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertCreated();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/properties/{$villa->id}", ['reason' => 'Annonce frauduleuse'])->assertNoContent();

        $this->assertSame(1, $client->notifications()->where('data', 'like', '%annul%')->count());
    }

    public function test_admin_cannot_delete_a_villa_with_a_paid_stay(): void
    {
        ['admin' => $admin, 'villa' => $villa] = $this->depositPaid();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/properties/{$villa->id}", ['reason' => 'Test de suppression'])
            ->assertStatus(422);

        $this->assertDatabaseHas('villas', ['id' => $villa->id]);
    }

    public function test_admin_lists_and_searches_clients_and_hosts(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('client', ['name' => 'Alice Rakoto', 'email' => 'alice@test.local']);
        $this->makeUser('client', ['name' => 'Bob Rabe', 'email' => 'bob@test.local']);
        $this->makeUser('host', ['name' => 'Hery Host', 'email' => 'hery@test.local']);

        $list = fn (string $q = '') => $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/users'.$q)->assertOk();

        $list()->assertJsonCount(3);                        // l'admin n'apparaît pas
        $list('?role=host')->assertJsonCount(1);
        $list('?search=ALICE')->assertJsonCount(1)->assertJsonPath('0.email', 'alice@test.local');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/users?role=admin')->assertStatus(422);
    }

    public function test_contact_messages_reach_the_admin_who_can_mark_them_read(): void
    {
        $admin = $this->makeUser('admin');

        $this->postJson('/api/v1/contact', ['name' => 'Visiteur', 'email' => 'v@test.local', 'subject' => 'Question', 'message' => 'Bonjour, une question sur une villa.'])
            ->assertCreated();

        $list = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/messages')->assertOk()->assertJsonCount(1)->assertJsonPath('0.read', false);
        $id = $list->json('0.id');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/messages/{$id}/read")->assertOk()->assertJsonPath('read', true);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/messages')->assertJsonPath('0.read', true);
    }
}
