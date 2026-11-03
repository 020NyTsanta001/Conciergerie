<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\Villa;
use Database\Seeders\Host2Seeder;
use Tests\TestCase;

class Host2SeederTest extends TestCase
{
    private function host2(): User
    {
        return User::where('email', Host2Seeder::EMAIL)->firstOrFail();
    }

    public function test_it_creates_one_host_with_five_approved_villas_three_of_them_with_a_yacht(): void
    {
        $this->seed(Host2Seeder::class);

        $host = $this->host2();
        $this->assertSame('host', $host->role);
        $this->assertNull($host->suspended_at);

        $villas = Villa::where('owner_id', $host->id)->with('images')->get();
        $this->assertCount(5, $villas);
        $this->assertSame(5, $villas->where('status', 'approved')->count());
        $this->assertSame(3, $villas->where('has_yacht', true)->count());
    }

    public function test_every_villa_has_three_different_photos(): void
    {
        $this->seed(Host2Seeder::class);

        foreach (Villa::where('owner_id', $this->host2()->id)->with('images')->get() as $villa) {
            $urls = $villa->images->pluck('url');
            $this->assertCount(3, $urls, $villa->title);
            $this->assertCount(3, $urls->unique(), $villa->title);
        }
    }

    public function test_yacht_details_are_complete_and_only_present_on_yacht_villas(): void
    {
        $this->seed(Host2Seeder::class);

        foreach (Villa::where('owner_id', $this->host2()->id)->get() as $villa) {
            if ($villa->has_yacht) {
                $this->assertNotEmpty($villa->yacht_name, $villa->title);
                $this->assertNotEmpty($villa->yacht_image, $villa->title);
                $this->assertNotEmpty($villa->yacht_description, $villa->title);
                $this->assertGreaterThanOrEqual(1, count($villa->yacht_places), $villa->title);
                $this->assertLessThanOrEqual(2, count($villa->yacht_places), $villa->title);
                $this->assertGreaterThan(0, $villa->yacht_price, $villa->title);
                $this->assertLessThanOrEqual(300, mb_strlen($villa->yacht_description), $villa->title);
            } else {
                foreach (['yacht_name', 'yacht_image', 'yacht_description', 'yacht_places', 'yacht_price'] as $field) {
                    $this->assertNull($villa->{$field}, "{$villa->title} : {$field}");
                }
            }
        }
    }

    public function test_service_prices_are_set_per_villa_and_some_services_are_not_offered(): void
    {
        $this->seed(Host2Seeder::class);
        $villas = Villa::where('owner_id', $this->host2()->id)->get();

        foreach ($villas as $villa) {
            foreach (['chef_price', 'chauffeur_price'] as $field) {
                if ($villa->{$field} !== null) {
                    $this->assertGreaterThan(0, $villa->{$field}, "{$villa->title} : {$field}");
                }
            }
        }
        $this->assertGreaterThanOrEqual(1, $villas->whereNull('chef_price')->count(), 'au moins une villa sans chef');
        $this->assertGreaterThanOrEqual(1, $villas->whereNull('chauffeur_price')->count(), 'au moins une villa sans chauffeur');
        $this->assertGreaterThanOrEqual(1, $villas->whereNotNull('chef_price')->whereNotNull('chauffeur_price')->count(), 'au moins une villa avec les deux');
        $this->assertGreaterThan(1, $villas->pluck('chef_price')->filter()->unique()->count(), 'les prix diffèrent d\'une villa à l\'autre');
    }

    public function test_running_it_twice_creates_no_duplicates(): void
    {
        $this->seed(Host2Seeder::class);
        $this->seed(Host2Seeder::class);

        $this->assertSame(1, User::where('email', Host2Seeder::EMAIL)->count());
        $this->assertSame(5, Villa::count());
        $this->assertDatabaseCount('images', 15);
    }

    public function test_existing_data_is_left_untouched(): void
    {
        $service = Service::create(['name' => 'Mon chef', 'type' => 'chef', 'price' => 777, 'is_active' => true]);
        $otherHost = $this->makeUser('host');
        $otherVilla = $this->makeVilla($otherHost, ['title' => 'Villa Existante', 'chef_price' => 12345]);
        $before = [$otherHost->fresh()->toArray(), $otherVilla->fresh()->toArray(), $service->fresh()->toArray()];

        $this->seed(Host2Seeder::class);

        $this->assertSame($before, [$otherHost->fresh()->toArray(), $otherVilla->fresh()->toArray(), $service->fresh()->toArray()]);
        $this->assertSame(1, Service::where('type', 'chef')->count(), 'le service du catalogue existant n\'est pas dupliqué');
        $this->assertSame(6, Villa::count());
    }

    public function test_it_adds_the_service_catalogue_when_missing(): void
    {
        $this->assertSame(0, Service::count());

        $this->seed(Host2Seeder::class);

        $this->assertEqualsCanonicalizing(['chef', 'chauffeur', 'yacht'], Service::pluck('type')->all());
    }

    public function test_seeded_villas_appear_in_the_catalogue_and_the_yacht_section(): void
    {
        $this->seed(Host2Seeder::class);

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/v1/properties?has_yacht=true')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/properties?q=baleine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Villa Sainte-Marie');
        $this->getJson('/api/v1/yachts')->assertOk()->assertJsonCount(3);
    }

    public function test_a_seeded_villa_can_be_quoted_and_booked_with_the_owner_prices(): void
    {
        $this->seed(Host2Seeder::class);
        $client = $this->makeUser('client');
        $ylang = Villa::where('title', 'Villa Ylang')->firstOrFail();
        $chef = Service::where('type', 'chef')->firstOrFail();
        $yacht = Service::where('type', 'yacht')->firstOrFail();
        $services = [['id' => $chef->id], ['id' => $yacht->id]];

        // 2 nuits à 450 000 + chef 120 000 + yacht 900 000 = 1 920 000 ; acompte 30 % = 576 000
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings/quote', $this->bookingPayload($ylang, 10, 2, ['services' => $services]))
            ->assertOk()->assertJsonPath('stay_total', 900000)->assertJsonPath('services_total', 1020000)
            ->assertJsonPath('total', 1920000)->assertJsonPath('deposit', 576000);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($ylang, 10, 2, ['services' => $services]))->assertCreated();
        $this->assertDatabaseHas('booking_service', ['service_id' => $yacht->id, 'unit_price' => 900000]);
    }

    public function test_a_service_that_a_seeded_villa_does_not_offer_is_refused(): void
    {
        $this->seed(Host2Seeder::class);
        $client = $this->makeUser('client');
        $lokobe = Villa::where('title', 'Villa Lokobe')->firstOrFail();   // pas de chauffeur
        $diego = Villa::where('title', 'Villa Diego Bleu')->firstOrFail(); // pas de chef, pas de yacht

        $chauffeur = Service::where('type', 'chauffeur')->firstOrFail();
        $chef = Service::where('type', 'chef')->firstOrFail();
        $yacht = Service::where('type', 'yacht')->firstOrFail();

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings/quote', $this->bookingPayload($lokobe, 10, 2, ['services' => [['id' => $chauffeur->id]]]))
            ->assertStatus(422)->assertJsonValidationErrors('services');
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings/quote', $this->bookingPayload($diego, 10, 2, ['services' => [['id' => $chef->id]]]))
            ->assertStatus(422)->assertJsonValidationErrors('services');
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings/quote', $this->bookingPayload($diego, 10, 2, ['services' => [['id' => $yacht->id]]]))
            ->assertStatus(422)->assertJsonValidationErrors('services');
    }
}
