<?php

namespace Tests\Feature;

use App\Models\Villa;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_name_can_be_changed(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/name', ['name' => 'Nouveau Nom'])->assertOk()->assertJsonPath('name', 'Nouveau Nom');
        $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/name', ['name' => ''])->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'name' => 'Nouveau Nom']);
    }

    public function test_password_change_needs_the_current_password_and_a_confirmation(): void
    {
        $client = $this->makeUser('client');
        $patch = fn (array $data) => $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/password', $data);

        $patch(['current_password' => 'faux', 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $patch(['current_password' => 'password123', 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'different'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $patch(['current_password' => 'password123', 'password' => 'court', 'password_confirmation' => 'court'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $patch(['current_password' => 'password123', 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1'])->assertOk();

        $this->postJson('/api/v1/login', ['email' => $client->email, 'password' => 'nouveau-mdp-1'])->assertOk();
        $this->postJson('/api/v1/login', ['email' => $client->email, 'password' => 'password123'])->assertStatus(422);
    }

    public function test_deleting_an_account_keeps_the_activity_log_entry(): void
    {
        $client = $this->makeUser('client', ['name' => 'Départ Volontaire']);

        $this->actingAs($client, 'sanctum')->deleteJson('/api/v1/account')->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $client->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_deleted', 'actor_name' => 'Départ Volontaire', 'user_id' => null]);
    }

    public function test_deleting_a_host_account_removes_his_villas_and_photos(): void
    {
        $host = $this->makeUser('host');
        foreach ([1, 2] as $n) {
            $villa = $this->makeVilla($host);
            $villa->images()->create(['url' => "https://img.test/{$n}.jpg", 'position' => 0]);
        }

        $this->actingAs($host, 'sanctum')->deleteJson('/api/v1/account')->assertNoContent();

        $this->assertSame(0, Villa::count());
        $this->assertDatabaseCount('images', 0);
    }

    public function test_an_account_with_a_paid_or_running_stay_cannot_be_deleted(): void
    {
        ['host' => $host, 'client' => $client] = $this->depositPaid();

        $this->actingAs($client, 'sanctum')->deleteJson('/api/v1/account')->assertStatus(422)->assertJsonValidationErrors('account');
        $this->actingAs($host, 'sanctum')->deleteJson('/api/v1/account')->assertStatus(422)->assertJsonValidationErrors('account');

        $this->assertDatabaseHas('users', ['id' => $client->id]);
        $this->assertDatabaseHas('users', ['id' => $host->id]);
    }

    public function test_the_admin_account_cannot_be_deleted_online(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/v1/account')->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
