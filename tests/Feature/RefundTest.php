<?php

namespace Tests\Feature;

use Tests\TestCase;

class RefundTest extends TestCase
{
    public function test_the_traveller_must_write_a_cause_to_ask_for_a_refund(): void
    {
        ['host' => $host, 'client' => $client, 'id' => $id] = $this->depositPaid();

        $this->status($client, $id, 'request_refund')->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->status($client, $id, 'request_refund', ['reason' => 'abc'])->assertStatus(422);
        $this->status($client, $id, 'request_refund', ['reason' => 'Empêchement familial'])
            ->assertOk()->assertJsonPath('data.refund.status', 'requested')->assertJsonPath('data.refund.reason', 'Empêchement familial');

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 3); // demande + acompte + remboursement
    }

    public function test_only_one_request_per_booking(): void
    {
        ['host' => $host, 'client' => $client, 'id' => $id] = $this->depositPaid();

        $this->status($client, $id, 'request_refund', ['reason' => 'Voyage annulé'])->assertOk();
        $this->status($client, $id, 'request_refund', ['reason' => 'Je redemande'])->assertStatus(422)->assertJsonValidationErrors('refund');

        $this->status($host, $id, 'decline_refund', ['reason' => 'Dates trop proches'])->assertOk();
        $this->status($client, $id, 'request_refund', ['reason' => 'Je redemande encore'])->assertStatus(422);
    }

    public function test_the_balance_is_blocked_while_a_request_is_pending(): void
    {
        ['client' => $client, 'id' => $id] = $this->depositPaid();

        $this->status($client, $id, 'request_refund', ['reason' => 'Voyage annulé'])->assertOk();

        $this->pay($client, $id, 'balance')->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_declining_needs_a_cause_which_the_traveller_receives(): void
    {
        ['host' => $host, 'client' => $client, 'id' => $id] = $this->depositPaid();
        $this->status($client, $id, 'request_refund', ['reason' => 'Voyage annulé'])->assertOk();

        $this->status($host, $id, 'decline_refund')->assertStatus(422);
        $this->status($host, $id, 'decline_refund', ['reason' => 'Dates trop proches'])
            ->assertOk()->assertJsonPath('data.refund.status', 'declined')->assertJsonPath('data.refund.decline_reason', 'Dates trop proches')
            ->assertJsonPath('data.status', 'deposit_paid');

        $this->assertSame(1, $client->notifications()->where('data', 'like', '%Dates trop proches%')->count());
    }

    public function test_after_a_refusal_the_traveller_can_still_pay_the_balance(): void
    {
        ['host' => $host, 'client' => $client, 'id' => $id] = $this->depositPaid();
        $this->status($client, $id, 'request_refund', ['reason' => 'Voyage annulé'])->assertOk();
        $this->status($host, $id, 'decline_refund', ['reason' => 'Dates trop proches'])->assertOk();

        $this->pay($client, $id, 'balance')->assertOk()->assertJsonPath('booking.data.status', 'balance_paid');
    }

    public function test_accepting_gives_the_deposit_back_and_frees_the_dates(): void
    {
        ['host' => $host, 'client' => $client, 'villa' => $villa, 'id' => $id] = $this->depositPaid();

        // sans demande préalable, l'hôte ne peut pas rembourser
        $this->status($host, $id, 'refund')->assertStatus(422);

        $this->status($client, $id, 'request_refund', ['reason' => 'Voyage annulé'])->assertOk();
        $this->status($host, $id, 'refund')->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refund.status', 'accepted');

        $this->assertSame(1_000_000, (int) $client->fresh()->balance);
        $this->assertSame(0, (int) $host->fresh()->balance);

        $other = $this->makeUser('client');
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertCreated();
    }

    public function test_no_refund_once_the_balance_is_paid(): void
    {
        ['client' => $client, 'id' => $id] = $this->depositPaid();
        $this->pay($client, $id, 'balance')->assertOk();

        $this->status($client, $id, 'request_refund', ['reason' => 'Trop tard ?'])->assertStatus(422)->assertJsonValidationErrors('refund');
    }

    public function test_no_refund_once_the_stay_date_is_reached(): void
    {
        ['client' => $client, 'id' => $id] = $this->depositPaid(0, 2); // séjour qui commence aujourd'hui

        $this->status($client, $id, 'request_refund', ['reason' => 'Trop tard ?'])->assertStatus(422)->assertJsonValidationErrors('refund');
        $this->assertDatabaseHas('bookings', ['id' => $id, 'status' => 'deposit_paid']);
    }

    public function test_only_the_traveller_of_the_booking_can_ask(): void
    {
        ['id' => $id] = $this->depositPaid();
        $stranger = $this->makeUser('client');

        $this->status($stranger, $id, 'request_refund', ['reason' => 'Je ne suis pas concerné'])->assertForbidden();
    }
}
