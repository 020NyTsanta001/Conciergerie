<?php

namespace Tests\Feature;

use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    public function test_a_request_waits_for_the_host_and_stores_the_services_at_the_owner_price(): void
    {
        $s = $this->seedServices();
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($host, ['chef_price' => 50000]);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10, 2, ['services' => [['id' => $s['chef']->id, 'quantity' => 1]]]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_host');
        $id = $response->json('data.id');
        $this->assertEquals(250000, $response->json('data.total_price'));   // 2 nuits à 100 000 + chef à 50 000
        $this->assertEquals(75000, $response->json('data.deposit_amount')); // 30 %

        $this->assertDatabaseHas('booking_service', ['booking_id' => $id, 'service_id' => $s['chef']->id, 'quantity' => 1, 'unit_price' => 50000]);
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 1);
        $this->assertGreaterThanOrEqual(1, $admin->notifications()->count());
    }

    public function test_full_stay_with_the_exact_split_between_host_and_platform(): void
    {
        $s = $this->seedServices();
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $villa = $this->makeVilla($host, ['chef_price' => 50000]);

        // 2 nuits à 100 000 + chef 50 000 = 250 000 ; acompte 30 % = 75 000 ; commission 15 % = 37 500 ; part de l'hôte = 212 500
        $id = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10, 2, ['services' => [['id' => $s['chef']->id]]]))
            ->assertCreated()->json('data.id');

        $this->status($host, $id, 'validate')->assertOk()->assertJsonPath('data.status', 'validated');
        $this->assertSame(1, $client->notifications()->count(), 'le voyageur est prévenu de l\'acceptation');

        $this->pay($client, $id, 'deposit')->assertOk()->assertJsonPath('booking.data.status', 'deposit_paid')->assertJsonPath('amount', 75000);
        $this->assertSame(925000, (int) $client->fresh()->balance);
        $this->assertSame(75000, (int) $host->fresh()->balance);
        $this->assertSame(0, (int) $admin->fresh()->balance);

        $this->pay($client, $id, 'balance')->assertOk()->assertJsonPath('booking.data.status', 'balance_paid')->assertJsonPath('amount', 175000);
        $this->assertSame(750000, (int) $client->fresh()->balance);
        $this->assertSame(212500, (int) $host->fresh()->balance);
        $this->assertSame(37500, (int) $admin->fresh()->balance);
        $this->assertDatabaseHas('payouts', ['booking_id' => $id, 'host_id' => $host->id, 'gross' => 250000, 'commission' => 37500, 'amount' => 212500]);

        $this->status($host, $id, 'start')->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->status($host, $id, 'close')->assertOk()->assertJsonPath('data.status', 'closed');

        $hostPayouts = $this->actingAs($host, 'sanctum')->getJson('/api/v1/host/payouts')->assertOk()->assertJsonCount(1, 'items');
        $this->assertEquals(212500, $hostPayouts->json('received'));
        $adminPayouts = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/payouts')->assertOk()->assertJsonCount(1, 'items');
        $this->assertEquals(37500, $adminPayouts->json('commission_total'));
    }

    public function test_balance_paid_on_site_debits_nobody_until_the_host_confirms(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $villa = $this->makeVilla($host);

        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->json('data.id');
        $this->status($host, $id, 'validate')->assertOk();
        $this->pay($client, $id, 'deposit')->assertOk(); // 200 000 : acompte 60 000, solde 140 000

        $this->pay($client, $id, 'balance', 'on_site')->assertOk()->assertJsonPath('booking.data.balance_method', 'on_site')->assertJsonPath('booking.data.status', 'deposit_paid');
        $this->assertSame(940000, (int) $client->fresh()->balance);

        $this->status($client, $id, 'confirm_onsite')->assertForbidden();
        $this->status($host, $id, 'confirm_onsite')->assertOk()->assertJsonPath('data.status', 'balance_paid');

        // commission 30 000 prélevée chez l'hôte (60 000 - 30 000), créditée à la plateforme
        $this->assertSame(940000, (int) $client->fresh()->balance);
        $this->assertSame(30000, (int) $host->fresh()->balance);
        $this->assertSame(30000, (int) $admin->fresh()->balance);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $villa = $this->makeVilla($host);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->json('data.id');

        $this->pay($client, $id, 'deposit')->assertStatus(422);       // pas encore validée
        $this->status($host, $id, 'validate')->assertOk();
        $this->pay($client, $id, 'balance')->assertStatus(422);       // acompte d'abord
        $this->status($host, $id, 'start')->assertForbidden();        // solde non payé
        $this->pay($client, $id, 'deposit')->assertOk();
        $this->pay($client, $id, 'deposit')->assertStatus(422);       // pas deux fois
        $this->status($host, $id, 'close')->assertForbidden();
    }

    public function test_deposit_is_refused_when_the_wallet_is_too_low(): void
    {
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1000);
        $villa = $this->makeVilla($host);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->json('data.id');
        $this->status($host, $id, 'validate')->assertOk();

        $this->pay($client, $id, 'deposit')->assertStatus(422)->assertJsonValidationErrors('balance');

        $this->assertSame(1000, (int) $client->fresh()->balance);
        $this->assertSame(0, (int) $host->fresh()->balance);
        $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'validated']);
    }

    public function test_dates_cannot_overlap_but_adjacent_stays_are_allowed(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'));
        $book = fn (int $from, int $nights) => $this->actingAs($this->makeUser('client'), 'sanctum')
            ->postJson('/api/v1/bookings', $this->bookingPayload($villa, $from, $nights));

        $book(10, 2)->assertCreated();                                                 // 10 -> 12
        $book(11, 2)->assertStatus(422)->assertJsonValidationErrors('check_in');       // 11 -> 13 : chevauche
        $book(9, 3)->assertStatus(422);                                                // 9 -> 12 : chevauche
        $book(12, 2)->assertCreated();                                                 // 12 -> 14 : la veille du départ est libre
        $book(8, 2)->assertCreated();                                                  // 8 -> 10 : idem
    }

    public function test_a_cancelled_booking_frees_its_dates(): void
    {
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host);
        $first = $this->makeUser('client');

        $id = $this->actingAs($first, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertCreated()->json('data.id');
        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertStatus(422);

        $this->status($first, $id, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertCreated();
    }

    public function test_capacity_is_enforced(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'), ['capacity' => 4]);

        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10, 2, ['guests' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('guests');
        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10, 2, ['guests' => 4]))->assertCreated();
    }

    public function test_only_travellers_book_and_only_approved_villas(): void
    {
        $host = $this->makeUser('host');
        $villa = $this->makeVilla($host);
        $pending = $this->makeVilla($host, ['status' => 'pending']);

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertForbidden();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertForbidden();
        $this->actingAs($this->makeUser('client'), 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($pending))->assertNotFound();
    }

    public function test_an_unknown_service_is_refused(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'));

        $this->actingAs($this->makeUser('client'), 'sanctum')
            ->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10, 2, ['services' => [['id' => 987654]]]))
            ->assertStatus(422)->assertJsonValidationErrors('services.0.id');
    }

    public function test_cancellation_is_possible_before_any_payment_only(): void
    {
        $admin = $this->makeUser('admin');
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $villa = $this->makeVilla($host);

        // le propriétaire refuse une demande : le voyageur est prévenu
        $refused = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa, 10))->json('data.id');
        $this->status($host, $refused, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(1, $client->notifications()->count());

        // après l'acompte, l'annulation simple n'existe plus (seule la demande de remboursement)
        $paid = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa, 20))->json('data.id');
        $this->status($host, $paid, 'validate')->assertOk();
        $this->pay($client, $paid, 'deposit')->assertOk();
        $this->status($client, $paid, 'cancel')->assertForbidden();
        $this->status($host, $paid, 'cancel')->assertForbidden();

        // l'admin consulte sans agir
        $this->status($admin, $paid, 'cancel')->assertForbidden();
    }

    public function test_each_profile_only_sees_its_own_bookings(): void
    {
        $admin = $this->makeUser('admin');
        $hostA = $this->makeUser('host');
        $hostB = $this->makeUser('host');
        $clientA = $this->makeUser('client');
        $clientB = $this->makeUser('client');
        $villaA = $this->makeVilla($hostA);
        $villaB = $this->makeVilla($hostB);

        $this->actingAs($clientA, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villaA))->assertCreated();
        $this->actingAs($clientB, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villaB))->assertCreated();

        $this->actingAs($clientA, 'sanctum')->getJson('/api/v1/bookings')->assertJsonCount(1, 'data');
        $this->actingAs($hostA, 'sanctum')->getJson('/api/v1/bookings')->assertJsonCount(1, 'data')->assertJsonPath('data.0.villa.id', $villaA->id);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/bookings')->assertJsonCount(2, 'data');
    }

    public function test_nobody_else_can_pay_or_validate_a_booking(): void
    {
        $host = $this->makeUser('host');
        $client = $this->fund($this->makeUser('client'), 1_000_000);
        $stranger = $this->fund($this->makeUser('client'), 1_000_000);
        $otherHost = $this->makeUser('host');
        $villa = $this->makeVilla($host);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->json('data.id');

        $this->status($client, $id, 'validate')->assertForbidden();
        $this->status($otherHost, $id, 'validate')->assertForbidden();
        $this->status($host, $id, 'validate')->assertOk();
        $this->pay($stranger, $id, 'deposit')->assertForbidden();
    }

    public function test_invalid_actions_are_rejected_by_validation(): void
    {
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($this->makeVilla($host)))->json('data.id');

        $this->status($host, $id, 'teleporter')->assertStatus(422)->assertJsonValidationErrors('action');
        $this->actingAs($client, 'sanctum')->postJson("/api/v1/bookings/{$id}/pay", ['type' => 'pourboire'])->assertStatus(422);
    }
}
