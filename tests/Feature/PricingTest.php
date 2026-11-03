<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Support\Money;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricingTest extends TestCase
{
    private function quote(\App\Models\User $as, \App\Models\Villa $villa, int $nights = 3, array $services = [], int $fromDays = 10)
    {
        return $this->actingAs($as, 'sanctum')->postJson('/api/v1/bookings/quote', $this->bookingPayload($villa, $fromDays, $nights, $services ? ['services' => $services] : []));
    }

    public function test_settings_live_in_the_config_files(): void
    {
        $this->assertSame(0.30, config('money.deposit_rate'));
        $this->assertSame(0.15, config('money.commission_rate'));
        $this->assertSame(5000, config('money.usd_rate'));
        $this->assertSame(['MGA', 'USD'], config('money.currencies'));
        $this->assertSame(5, config('booking.max_nights'));
    }

    public function test_money_helpers_round_to_whole_ariary(): void
    {
        $this->assertSame(75000, Money::deposit(250000));
        $this->assertSame(37500, Money::commission(250000));
        $this->assertSame(300, Money::deposit(1001));
        $this->assertSame('1 234 567 Ar', Money::format(1234567));
    }

    public function test_seasonal_prices_no_longer_exist(): void
    {
        $this->assertFalse(Schema::hasTable('seasons'));
        $this->assertFalse(Schema::hasTable('villa_season'));
        $this->assertTrue(Schema::hasColumns('villas', [
            'price_per_night', 'chef_price', 'chauffeur_price', 'yacht_price',
            'has_yacht', 'yacht_image', 'yacht_name', 'yacht_description', 'yacht_places',
        ]));
    }

    public function test_stay_price_is_the_nightly_price_times_the_number_of_nights(): void
    {
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($this->makeUser('host'), ['price_per_night' => 150000]);

        $this->quote($client, $villa, 3)->assertOk()
            ->assertJsonPath('nights_count', 3)
            ->assertJsonPath('stay_total', 450000)
            ->assertJsonPath('services_total', 0)
            ->assertJsonPath('total', 450000)
            ->assertJsonPath('deposit', 135000);
    }

    public function test_services_are_charged_at_the_price_set_by_the_villa_owner(): void
    {
        $s = $this->seedServices();
        $client = $this->makeUser('client');
        $host = $this->makeUser('host');
        $cheap = $this->makeVilla($host, ['chef_price' => 120000]);
        $dear = $this->makeVilla($host, ['chef_price' => 200000]);

        // même service du catalogue, deux prix différents selon la villa : 2 nuits à 100 000 + 2 chefs
        $line = [['id' => $s['chef']->id, 'quantity' => 2]];

        $cheapQuote = $this->quote($client, $cheap, 2, $line)->assertOk()
            ->assertJsonPath('services_total', 240000)->assertJsonPath('total', 440000);
        $this->assertEquals(120000, $cheapQuote->json('services.0.unit_price'));
        $this->quote($client, $dear, 2, $line)->assertOk()
            ->assertJsonPath('services_total', 400000)->assertJsonPath('total', 600000);
    }

    public function test_the_catalogue_price_is_ignored(): void
    {
        $s = $this->seedServices();
        Service::where('type', 'chef')->update(['price' => 999999]);
        $villa = $this->makeVilla($this->makeUser('host'), ['chef_price' => 50000]);

        $this->quote($this->makeUser('client'), $villa, 1, [['id' => $s['chef']->id]])->assertOk()->assertJsonPath('services_total', 50000);
    }

    public function test_a_service_the_owner_does_not_offer_is_refused(): void
    {
        $s = $this->seedServices();
        $villa = $this->makeVilla($this->makeUser('host'), ['chef_price' => 100000, 'chauffeur_price' => null]);

        $this->quote($this->makeUser('client'), $villa, 2, [['id' => $s['chauffeur']->id]])
            ->assertStatus(422)->assertJsonValidationErrors('services');
    }

    public function test_the_yacht_trip_exists_only_on_a_villa_that_has_a_yacht(): void
    {
        $s = $this->seedServices();
        $client = $this->makeUser('client');
        $host = $this->makeUser('host');
        $noYacht = $this->makeVilla($host, ['has_yacht' => false, 'yacht_price' => 500000]);
        $withYacht = $this->makeYachtVilla($host, ['yacht_price' => 400000]);

        $this->quote($client, $noYacht, 2, [['id' => $s['yacht']->id]])->assertStatus(422)->assertJsonValidationErrors('services');
        $this->quote($client, $withYacht, 2, [['id' => $s['yacht']->id]])->assertOk()
            ->assertJsonPath('services_total', 400000)->assertJsonPath('total', 600000);
    }

    public function test_an_inactive_service_is_left_out_of_the_quote(): void
    {
        $s = $this->seedServices();
        Service::where('type', 'chef')->update(['is_active' => false]);
        $villa = $this->makeVilla($this->makeUser('host'), ['chef_price' => 100000]);

        $this->quote($this->makeUser('client'), $villa, 2, [['id' => $s['chef']->id]])->assertOk()
            ->assertJsonPath('services_total', 0)->assertJsonPath('total', 200000);
    }

    public function test_a_stay_lasts_five_nights_at_most(): void
    {
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($this->makeUser('host'));

        $this->quote($client, $villa, 5)->assertOk()->assertJsonPath('nights_count', 5);
        $this->quote($client, $villa, 6)->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    public function test_dates_are_validated(): void
    {
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($this->makeUser('host'));
        $post = fn (array $dates) => $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings/quote', $dates + ['villa_id' => $villa->id, 'guests' => 2]);

        $post(['check_in' => $this->day(10), 'check_out' => $this->day(10)])->assertStatus(422)->assertJsonValidationErrors('check_out');
        $post(['check_in' => $this->day(10), 'check_out' => $this->day(8)])->assertStatus(422)->assertJsonValidationErrors('check_out');
        $post(['check_in' => $this->day(-2), 'check_out' => $this->day(1)])->assertStatus(422)->assertJsonValidationErrors('check_in');
    }

    public function test_only_a_traveller_can_ask_for_a_quote(): void
    {
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host);

        // d'abord sans compte : actingAs() reste actif jusqu'à la fin du test
        $this->postJson('/api/v1/bookings/quote', $this->bookingPayload($villa))->assertUnauthorized();
        $this->quote($host, $villa)->assertForbidden();
        $this->quote($this->makeUser('admin'), $villa)->assertForbidden();
    }

    public function test_a_villa_awaiting_moderation_cannot_be_quoted(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'), ['status' => 'pending']);

        $this->quote($this->makeUser('client'), $villa)->assertNotFound();
    }
}
