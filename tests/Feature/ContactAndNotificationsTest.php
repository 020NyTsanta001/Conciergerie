<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactAndNotificationsTest extends TestCase
{
    private function message(array $over = []): array
    {
        return $over + ['name' => 'Visiteur', 'email' => 'visiteur@test.local', 'subject' => 'Question', 'message' => 'Bonjour, j\'ai une question sur une villa.'];
    }

    public function test_contact_form_is_validated(): void
    {
        $this->postJson('/api/v1/contact', $this->message(['message' => 'court']))->assertStatus(422)->assertJsonValidationErrors('message');
        $this->postJson('/api/v1/contact', $this->message(['email' => 'pas-un-mail']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/contact', $this->message(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/contact', $this->message(['subject' => null]))->assertCreated();
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_form_is_limited_to_five_messages_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/contact', $this->message())->assertCreated();
        }

        $this->postJson('/api/v1/contact', $this->message())->assertStatus(429);
    }

    public function test_registration_is_limited_to_ten_a_minute(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/register', ['name' => "U{$i}", 'email' => "u{$i}@test.local", 'password' => 'password123', 'role' => 'client'])->assertCreated();
        }

        $this->postJson('/api/v1/register', ['name' => 'U11', 'email' => 'u11@test.local', 'password' => 'password123', 'role' => 'client'])->assertStatus(429);
    }

    public function test_a_red_dot_lights_up_per_area_and_clears_when_read(): void
    {
        $admin = $this->makeUser('admin');
        $this->postJson('/api/v1/contact', $this->message())->assertCreated();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications/dots')->assertOk()->assertJsonPath('messages', 1);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/notifications/read', ['area' => 'messages'])->assertNoContent();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/notifications/dots')->assertOk()->assertJsonMissingPath('messages');
    }

    public function test_an_unknown_area_is_refused(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/notifications/read', ['area' => 'nimportequoi'])->assertStatus(422);
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/notifications/read', [])->assertStatus(422);
    }

    public function test_dots_are_private_to_each_account(): void
    {
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($host);

        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($villa))->assertCreated();

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonPath('bookings', 1);
        $this->actingAs($client, 'sanctum')->getJson('/api/v1/notifications/dots')->assertJsonMissingPath('bookings');
    }

    public function test_notification_list_exposes_area_and_read_state(): void
    {
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $this->actingAs($client, 'sanctum')->postJson('/api/v1/bookings', $this->bookingPayload($this->makeVilla($host)))->assertCreated();

        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.area', 'bookings')->assertJsonPath('0.read', false);

        $this->actingAs($host, 'sanctum')->postJson('/api/v1/notifications/read', ['area' => 'bookings'])->assertNoContent();
        $this->actingAs($host, 'sanctum')->getJson('/api/v1/notifications')->assertJsonPath('0.read', true);
    }
}
