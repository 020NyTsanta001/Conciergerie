<?php

namespace Tests\Feature;

use App\Models\Service;
use Tests\TestCase;

class VillaCatalogueTest extends TestCase
{
    public function test_only_approved_villas_are_public(): void
    {
        $host = $this->makeUser('host');
        $approved = $this->makeVilla($host);
        $pending = $this->makeVilla($host, ['status' => 'pending']);
        $rejected = $this->makeVilla($host, ['status' => 'rejected']);

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->id);
        $this->getJson("/api/v1/properties/{$approved->id}")->assertOk();
        $this->getJson("/api/v1/properties/{$pending->id}")->assertNotFound();
        $this->getJson("/api/v1/properties/{$rejected->id}")->assertNotFound();
    }

    public function test_villa_page_exposes_owner_photos_and_service_prices(): void
    {
        $host = $this->makeUser('host', ['name' => 'Nirina Test']);
        $villa = $this->makeVilla($host, ['chef_price' => 120000, 'chauffeur_price' => null]);
        foreach ([0, 1, 2] as $i) {
            $villa->images()->create(['url' => "https://img.test/{$i}.jpg", 'position' => $i]);
        }

        $this->getJson("/api/v1/properties/{$villa->id}")->assertOk()
            ->assertJsonPath('data.owner.name', 'Nirina Test')
            ->assertJsonPath('data.images', ['https://img.test/0.jpg', 'https://img.test/1.jpg', 'https://img.test/2.jpg'])
            ->assertJsonPath('data.chef_price', 120000)
            ->assertJsonPath('data.chauffeur_price', null)
            ->assertJsonPath('data.yacht_price', null);
    }

    public function test_filters_and_free_search(): void
    {
        $host = $this->makeUser('host');
        $this->makeVilla($host, ['title' => 'Villa Ylang', 'city' => 'Nosy Be', 'price_per_night' => 300000, 'capacity' => 4, 'has_pool' => true, 'is_featured' => true]);
        $this->makeYachtVilla($host, ['title' => 'Villa Lokobe', 'city' => 'Nosy Be', 'price_per_night' => 500000, 'capacity' => 8, 'has_pool' => false,
            'yacht_name' => 'Lokobe Spirit', 'yacht_places' => ['Nosy Komba']]);
        $this->makeVilla($host, ['title' => 'Villa Baobab', 'city' => 'Morondava', 'price_per_night' => 700000, 'capacity' => 10, 'has_pool' => true]);

        $count = fn (string $query) => count($this->getJson("/api/v1/properties?{$query}")->assertOk()->json('data'));

        $this->assertSame(2, $count('city=nosy'), 'ville : recherche partielle');
        $this->assertSame(1, $count('city=MORONDAVA'), 'ville : insensible à la casse');
        $this->assertSame(2, $count('price_min=500000'));
        $this->assertSame(2, $count('price_max=500000'));
        $this->assertSame(1, $count('price_min=400000&price_max=600000'));
        $this->assertSame(2, $count('guests=8'));
        $this->assertSame(0, $count('guests=11'));
        $this->assertSame(2, $count('has_pool=true'));
        $this->assertSame(1, $count('featured=true'));
        $this->assertSame(1, $count('has_yacht=true'));
        $this->assertSame(3, $count('has_yacht=false'), 'false = pas de filtre');

        $this->assertSame(1, $count('q=lokobe'), 'recherche : titre');
        $this->assertSame(1, $count('q=spirit'), 'recherche : nom du yacht');
        $this->assertSame(1, $count('q=komba'), 'recherche : lieu de sortie du yacht (colonne JSON)');
        $this->assertSame(3, $count('q=madagascar'), 'recherche : pays');
    }

    public function test_catalogue_is_paginated_by_twelve(): void
    {
        $host = $this->makeUser('host');
        for ($i = 0; $i < 13; $i++) {
            $this->makeVilla($host);
        }

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 13);
        $this->getJson('/api/v1/properties?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_random_mode_only_returns_villas_with_a_photo(): void
    {
        $host = $this->makeUser('host');
        $withPhoto = $this->makeVilla($host);
        $withPhoto->images()->create(['url' => 'https://img.test/a.jpg', 'position' => 0]);
        $other = $this->makeVilla($host);
        $other->images()->create(['url' => 'https://img.test/b.jpg', 'position' => 0]);
        $this->makeVilla($host); // sans photo

        $this->getJson('/api/v1/properties?random=5')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/properties?random=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_yachts_come_from_approved_villas_that_have_one(): void
    {
        $host = $this->makeUser('host');
        $this->makeYachtVilla($host, ['title' => 'Villa A', 'yacht_name' => 'Alpha']);
        $this->makeYachtVilla($host, ['title' => 'Villa B', 'yacht_name' => null]); // nom absent : nom par défaut
        $this->makeYachtVilla($host, ['status' => 'pending']);                     // non approuvée
        $this->makeYachtVilla($host, ['yacht_image' => null]);                      // sans photo de yacht
        $this->makeVilla($host);                                                    // sans yacht

        $names = collect($this->getJson('/api/v1/yachts')->assertOk()->assertJsonCount(2)->json())->pluck('name')->all();

        $this->assertContains('Alpha', $names);
        $this->assertContains('Yacht de Villa B', $names);
    }

    public function test_yacht_list_is_limited_to_four(): void
    {
        $host = $this->makeUser('host');
        for ($i = 0; $i < 5; $i++) {
            $this->makeYachtVilla($host);
        }

        $this->getJson('/api/v1/yachts')->assertOk()->assertJsonCount(4);
    }

    public function test_services_lists_only_active_ones(): void
    {
        $this->seedServices();
        Service::where('type', 'chauffeur')->update(['is_active' => false]);

        $types = collect($this->getJson('/api/v1/services')->assertOk()->json())->pluck('type')->sort()->values()->all();

        $this->assertSame(['chef', 'yacht'], $types);
    }

    public function test_home_stats_count_approved_villas_only(): void
    {
        $host = $this->makeUser('host');
        $this->makeVilla($host);
        $this->makeVilla($host);
        $this->makeVilla($host, ['status' => 'pending']);

        $this->getJson('/api/v1/stats')->assertOk()->assertJsonPath('properties', 2)->assertJsonPath('bookings', 0);
    }

    public function test_reviews_of_a_non_approved_villa_are_hidden(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'), ['status' => 'pending']);

        $this->getJson("/api/v1/properties/{$villa->id}/reviews")->assertNotFound();
    }
}
