<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthAndRolesTest extends TestCase
{
    public function test_visitor_registers_as_client_or_host(): void
    {
        foreach (['client', 'host'] as $role) {
            $this->postJson('/api/v1/register', [
                'name' => "Nouveau {$role}", 'email' => "{$role}@inscription.test", 'password' => 'password123', 'role' => $role,
            ])->assertCreated()->assertJsonStructure(['user', 'token']);

            $this->assertDatabaseHas('users', ['email' => "{$role}@inscription.test", 'role' => $role]);
        }
    }

    public function test_admin_cannot_be_created_by_public_registration(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Pirate', 'email' => 'pirate@test.local', 'password' => 'password123', 'role' => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'pirate@test.local']);
    }

    public function test_registration_rejects_short_password_and_duplicate_email(): void
    {
        $existing = $this->makeUser('client');

        $this->postJson('/api/v1/register', ['name' => 'A', 'email' => 'court@test.local', 'password' => 'court', 'role' => 'client'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/register', ['name' => 'B', 'email' => $existing->email, 'password' => 'password123', 'role' => 'client'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_gives_a_token_that_opens_the_profile(): void
    {
        $user = $this->makeUser('client');

        $token = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', $user->email);
    }

    public function test_wrong_password_is_refused(): void
    {
        $user = $this->makeUser('client');

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser('client');
        $token = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password123'])->json('token');
        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_roles_protect_host_and_admin_areas(): void
    {
        $client = $this->makeUser('client');
        $host = $this->makeUser('host');
        $admin = $this->makeUser('admin');

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/host/properties')->assertForbidden();
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/admin/properties')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/host/properties')->assertForbidden();

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/host/properties')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/properties')->assertOk();
    }

    public function test_admin_suspends_then_reactivates_an_account(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('client');
        $client->createToken('ancienne-session');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/users/{$client->id}/suspend", ['suspended' => true])
            ->assertOk()->assertJsonPath('suspended', true);

        $this->assertSame(0, $client->tokens()->count(), 'les sessions ouvertes sont coupées');
        $this->postJson('/api/v1/login', ['email' => $client->email, 'password' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/users/{$client->id}/suspend", ['suspended' => false])
            ->assertOk()->assertJsonPath('suspended', false);
        $this->postJson('/api/v1/login', ['email' => $client->email, 'password' => 'password123'])->assertOk();
    }

    public function test_an_admin_account_cannot_be_suspended(): void
    {
        $admin = $this->makeUser('admin');
        $other = $this->makeUser('admin');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/users/{$other->id}/suspend", ['suspended' => true])->assertForbidden();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = $this->makeUser('client');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'faux'])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password123'])->assertStatus(429);
    }

    public function test_actions_are_traced_in_the_activity_log_for_the_admin_only(): void
    {
        $admin = $this->makeUser('admin');
        $client = $this->makeUser('client');
        $this->postJson('/api/v1/login', ['email' => $client->email, 'password' => 'password123'])->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $client->id, 'actor_role' => 'client']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/activity?role=client')
            ->assertOk()->assertJsonFragment(['action' => 'login']);
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/admin/activity')->assertForbidden();
    }
}
