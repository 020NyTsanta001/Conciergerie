<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class WalletTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_a_new_wallet_is_empty_and_lists_the_available_bonuses(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonPath('balance', 0)
            ->assertJsonPath('claimed', [])
            ->assertJsonPath('rewards.cv', 1000000)
            ->assertJsonStructure(['rewards' => ['about', 'github', 'org_github', 'cv'], 'pacman' => ['max_paid_games', 'games_played'], 'transactions']);
    }

    public function test_each_bonus_is_claimed_once_per_account(): void
    {
        $client = $this->makeUser('client');
        $other = $this->makeUser('client');

        foreach (['about', 'github', 'org_github', 'cv'] as $i => $key) {
            $this->actingAs($client, 'sanctum')->postJson('/api/v1/wallet/claim', ['key' => $key])
                ->assertOk()->assertJsonPath('amount', 1000000)->assertJsonPath('balance', 1000000 * ($i + 1));
        }

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/wallet/claim', ['key' => 'cv'])->assertStatus(422);
        $this->assertSame(4_000_000, (int) $client->fresh()->balance);

        // le même bonus reste disponible pour un autre compte
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/wallet/claim', ['key' => 'cv'])->assertOk();
        $this->assertSame(1_000_000, (int) $other->fresh()->balance);
    }

    public function test_unknown_bonus_is_refused_and_movements_are_recorded(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/wallet/claim', ['key' => 'inconnu'])->assertStatus(422);
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/wallet/claim', ['key' => 'about'])->assertOk();

        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $client->id, 'type' => 'reward', 'amount' => 1000000, 'balance_after' => 1000000, 'reward_key' => 'about']);
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/wallet')->assertJsonPath('claimed', ['about'])->assertJsonCount(1, 'transactions');
    }

    public function test_display_currency_can_be_changed_except_for_the_admin(): void
    {
        $client = $this->makeUser('client');
        $host = $this->makeUser('host');
        $admin = $this->makeUser('admin');

        $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/currency', ['currency' => 'USD'])->assertOk()->assertJsonPath('currency', 'USD');
        $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/currency', ['currency' => 'MGA'])->assertOk()->assertJsonPath('currency', 'MGA');
        $this->actingAs($host, 'sanctum')->patchJson('/api/v1/account/currency', ['currency' => 'USD'])->assertOk();
        $this->actingAs($client, 'sanctum')->patchJson('/api/v1/account/currency', ['currency' => 'EUR'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/account/currency', ['currency' => 'USD'])->assertForbidden();
    }

    public function test_pacman_pays_a_step_every_twenty_seconds_for_two_games_only(): void
    {
        $client = $this->makeUser('client');

        // partie 1 : 45 s = 2 paliers = 2 000 000 Ar
        $this->travelTo(Carbon::parse('2027-01-01 10:00:00'));
        $first = $this->actingAs($client, 'sanctum')->postJson('/api/v1/games/pacman/start')->assertOk()->assertJsonPath('paid', true)->json('id');
        $this->travelTo(Carbon::parse('2027-01-01 10:00:45'));
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/games/pacman/{$first}/finish")->assertOk()
            ->assertJsonPath('seconds', 45)->assertJsonPath('amount', 2000000)->assertJsonPath('balance', 2000000);

        // une partie ne se termine qu'une fois
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/games/pacman/{$first}/finish")->assertStatus(422);

        // partie 2 : le gain est plafonné à 5 paliers
        $this->travelTo(Carbon::parse('2027-01-01 11:00:00'));
        $second = $this->actingAs($client, 'sanctum')->postJson('/api/v1/games/pacman/start')->assertOk()->assertJsonPath('paid', true)->json('id');
        $this->travelTo(Carbon::parse('2027-01-01 11:10:00'));
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/games/pacman/{$second}/finish")->assertOk()
            ->assertJsonPath('amount', 5000000)->assertJsonPath('balance', 7000000);

        // partie 3 : plus rémunérée
        $this->travelTo(Carbon::parse('2027-01-01 12:00:00'));
        $third = $this->actingAs($client, 'sanctum')->postJson('/api/v1/games/pacman/start')->assertOk()->assertJsonPath('paid', false)->json('id');
        $this->travelTo(Carbon::parse('2027-01-01 12:01:40'));
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/games/pacman/{$third}/finish")->assertOk()
            ->assertJsonPath('amount', 0)->assertJsonPath('paid', false);

        $this->assertSame(7_000_000, (int) $client->fresh()->balance);
    }

    public function test_a_game_cannot_be_finished_by_someone_else(): void
    {
        $owner = $this->makeUser('client');
        $thief = $this->makeUser('client');

        $id = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/games/pacman/start')->json('id');

        $this->actingAs($thief, 'sanctum')->postJson("/api/v1/games/pacman/{$id}/finish")->assertForbidden();
    }
}
