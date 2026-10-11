<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAndLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => $role.' Test', 'email' => $role.uniqid().'@t.test', 'password' => 'password', 'role' => $role]);
    }

    public function test_reviews_are_listed_on_a_villa_without_seasons(): void
    {
        $host = $this->user('host');
        $client = $this->user('client');
        $villa = Villa::create([
            'owner_id' => $host->id, 'title' => 'Villa Avis', 'description' => 'x', 'city' => 'Nice', 'country' => 'France',
            'price_per_night' => 1000, 'bedrooms' => 2, 'capacity' => 4, 'status' => 'approved',
        ]);
        $booking = Booking::create([
            'user_id' => $client->id, 'villa_id' => $villa->id, 'check_in' => '2027-03-10', 'check_out' => '2027-03-12',
            'guests' => 2, 'status' => 'closed', 'total_price' => 2000, 'deposit_amount' => 600, 'paid_amount' => 2000,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Superbe séjour'])
            ->assertCreated();

        $this->getJson("/api/v1/properties/{$villa->id}/reviews")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.comment', 'Superbe séjour');
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = $this->user('client');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'faux'])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }
}
