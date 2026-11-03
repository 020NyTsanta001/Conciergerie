<?php

namespace Tests\Feature;

use Tests\TestCase;

class HostVillaTest extends TestCase
{
    private function payload(array $over = []): array
    {
        return $over + [
            'title' => 'Villa Nouvelle', 'description' => 'Belle villa', 'city' => 'Nosy Be', 'country' => 'Madagascar',
            'price_per_night' => 400000, 'bedrooms' => 3, 'capacity' => 6, 'has_pool' => true,
            'images' => ['https://img.test/a.jpg', 'https://img.test/b.jpg', 'https://img.test/c.jpg'],
        ];
    }

    private function yachtFields(array $over = []): array
    {
        return $over + [
            'has_yacht' => true, 'yacht_image' => 'https://img.test/yacht.jpg', 'yacht_name' => 'Ylang Voyager',
            'yacht_description' => 'Catamaran de 14 m', 'yacht_places' => ['Nosy Komba', 'Nosy Tanikely'], 'yacht_price' => 900000,
        ];
    }

    public function test_new_villa_waits_for_moderation_and_alerts_the_admin(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload())
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonCount(3, 'data.images');

        $this->getJson('/api/v1/properties')->assertJsonCount(0, 'data');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('moderation', 1);
    }

    public function test_villa_needs_exactly_three_different_photos(): void
    {
        $host = $this->makeUser('host');
        $post = fn (array $images) => $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload(['images' => $images]));

        $post(['https://img.test/a.jpg', 'https://img.test/b.jpg'])->assertStatus(422)->assertJsonValidationErrors('images');
        $post(['https://img.test/a.jpg', 'https://img.test/b.jpg', 'https://img.test/c.jpg', 'https://img.test/d.jpg'])->assertStatus(422);
        $post(['https://img.test/a.jpg', 'https://img.test/a.jpg', 'https://img.test/c.jpg'])->assertStatus(422);
        $post(['https://img.test/a.jpg', 'pas-un-lien', 'https://img.test/c.jpg'])->assertStatus(422);
    }

    public function test_basic_fields_are_validated(): void
    {
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload(['price_per_night' => 0, 'capacity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors(['price_per_night', 'capacity']);
    }

    public function test_yacht_option_requires_all_its_details(): void
    {
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload(['has_yacht' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['yacht_image', 'yacht_name', 'yacht_description', 'yacht_places', 'yacht_price']);
    }

    public function test_yacht_accepts_one_or_two_places_not_three(): void
    {
        $host = $this->makeUser('host');
        $post = fn (array $places) => $this->actingAs($host, 'sanctum')
            ->postJson('/api/v1/host/properties', $this->payload($this->yachtFields(['yacht_places' => $places])));

        $post(['Nosy Komba'])->assertCreated();
        $post(['Nosy Komba', 'Nosy Tanikely'])->assertCreated();
        $post(['A', 'B', 'C'])->assertStatus(422)->assertJsonValidationErrors('yacht_places');
    }

    public function test_complete_yacht_is_stored_with_its_price(): void
    {
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload($this->yachtFields()))
            ->assertCreated()
            ->assertJsonPath('data.has_yacht', true)
            ->assertJsonPath('data.yacht_name', 'Ylang Voyager')
            ->assertJsonPath('data.yacht_places', ['Nosy Komba', 'Nosy Tanikely'])
            ->assertJsonPath('data.yacht_price', 900000);
    }

    public function test_without_yacht_no_yacht_information_is_kept(): void
    {
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/v1/host/properties', $this->payload(['has_yacht' => false, 'yacht_name' => 'Fantôme', 'yacht_price' => 500000]))
            ->assertCreated()->assertJsonPath('data.has_yacht', false)->assertJsonPath('data.yacht_name', null)->assertJsonPath('data.yacht_price', null);

        $this->assertDatabaseHas('villas', ['title' => 'Villa Nouvelle', 'has_yacht' => false, 'yacht_name' => null, 'yacht_price' => null]);
    }

    public function test_owner_sets_the_price_of_each_service(): void
    {
        $host = $this->makeUser('host');

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/v1/host/properties', $this->payload(['chef_price' => 120000, 'chauffeur_price' => 80000]))
            ->assertCreated()->assertJsonPath('data.chef_price', 120000)->assertJsonPath('data.chauffeur_price', 80000);

        $this->actingAs($host, 'sanctum')
            ->postJson('/api/v1/host/properties', $this->payload(['title' => 'Sans services']))
            ->assertCreated()->assertJsonPath('data.chef_price', null)->assertJsonPath('data.chauffeur_price', null);
    }

    public function test_service_prices_must_be_positive_whole_numbers(): void
    {
        $host = $this->makeUser('host');

        foreach ([['chef_price' => 0], ['chef_price' => -5], ['chauffeur_price' => 100.5], ['chauffeur_price' => 'cher']] as $bad) {
            $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload($bad))->assertStatus(422);
        }
    }

    public function test_host_only_lists_his_own_villas(): void
    {
        $host = $this->makeUser('host');
        $other = $this->makeUser('host');
        $this->makeVilla($host);
        $this->makeVilla($host, ['status' => 'pending']);
        $this->makeVilla($other);

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/host/properties')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_editing_sends_the_villa_back_to_moderation(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $id = $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload())->json('data.id');
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/properties/{$id}/moderate", ['status' => 'approved'])->assertOk();
        $this->getJson('/api/v1/properties')->assertJsonCount(1, 'data');

        $this->actingAs($host, 'sanctum')->putJson("/api/v1/host/properties/{$id}", $this->payload(['title' => 'Villa Rénovée']))
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.title', 'Villa Rénovée');

        $this->getJson('/api/v1/properties')->assertJsonCount(0, 'data');
    }

    public function test_editing_the_photos_replaces_them(): void
    {
        $host = $this->makeUser('host');
        $id = $this->actingAs($host, 'sanctum')->postJson('/api/v1/host/properties', $this->payload())->json('data.id');

        $new = ['https://img.test/x.jpg', 'https://img.test/y.jpg', 'https://img.test/z.jpg'];
        $this->actingAs($host, 'sanctum')->putJson("/api/v1/host/properties/{$id}", $this->payload(['images' => $new]))
            ->assertOk()->assertJsonPath('data.images', $new);

        $this->assertDatabaseCount('images', 3);
    }

    public function test_only_the_creator_can_edit_or_delete_a_villa(): void
    {
        $owner = $this->makeUser('host');
        $other = $this->makeUser('host');
        $villa = $this->makeVilla($owner);

        $this->actingAs($other, 'sanctum')->putJson("/api/v1/host/properties/{$villa->id}", $this->payload())->assertForbidden();
        $this->actingAs($other, 'sanctum')->deleteJson("/api/v1/host/properties/{$villa->id}")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/host/properties/{$villa->id}")->assertNoContent();

        $this->assertDatabaseMissing('villas', ['id' => $villa->id]);
    }

    public function test_a_villa_with_a_paid_stay_cannot_be_deleted(): void
    {
        ['host' => $host, 'villa' => $villa] = $this->depositPaid();

        $this->actingAs($host, 'sanctum')->deleteJson("/api/v1/host/properties/{$villa->id}")
            ->assertStatus(422)->assertJsonValidationErrors('villa');

        $this->assertDatabaseHas('villas', ['id' => $villa->id]);
    }
}
