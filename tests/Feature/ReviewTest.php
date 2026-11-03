<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Villa;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    private function stay(User $client, Villa $villa, string $status = 'closed'): Booking
    {
        return Booking::create([
            'user_id' => $client->id, 'villa_id' => $villa->id,
            'check_in' => $this->day(-10), 'check_out' => $this->day(-8), 'guests' => 2,
            'status' => $status, 'total_price' => 200000, 'deposit_amount' => 60000, 'paid_amount' => 200000,
        ]);
    }

    private function review(User $as, Booking $booking, array $data)
    {
        return $this->actingAs($as, 'sanctum')->postJson("/api/v1/bookings/{$booking->id}/review", $data);
    }

    public function test_a_review_is_only_possible_once_the_stay_is_closed(): void
    {
        $client = $this->makeUser('client');
        $villa = $this->makeVilla($this->makeUser('host'));

        foreach (['pending_host', 'validated', 'in_progress', 'cancelled'] as $status) {
            $this->review($client, $this->stay($client, $villa, $status), ['rating' => 5])->assertStatus(422)->assertJsonValidationErrors('review');
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_the_traveller_reviews_a_closed_stay_once(): void
    {
        $client = $this->makeUser('client', ['name' => 'Alice Rakoto Longnom']);
        $villa = $this->makeVilla($this->makeUser('host'));
        $booking = $this->stay($client, $villa);

        $this->review($client, $booking, ['rating' => 5, 'comment' => 'Séjour parfait'])
            ->assertCreated()->assertJsonPath('rating', 5)->assertJsonPath('comment', 'Séjour parfait')->assertJsonPath('author', 'Alice Rakoto');
        $this->review($client, $booking, ['rating' => 4])->assertStatus(422)->assertJsonValidationErrors('review');

        $list = $this->getJson("/api/v1/properties/{$villa->id}/reviews")->assertOk()->assertJsonPath('count', 1);
        $this->assertEquals(5, $list->json('average'));
    }

    public function test_rating_and_comment_are_validated(): void
    {
        $client = $this->makeUser('client');
        $booking = $this->stay($client, $this->makeVilla($this->makeUser('host')));

        foreach ([0, 6, 'x', null] as $bad) {
            $this->review($client, $booking, ['rating' => $bad])->assertStatus(422)->assertJsonValidationErrors('rating');
        }
        $this->review($client, $booking, ['rating' => 4, 'comment' => str_repeat('a', 301)])->assertStatus(422)->assertJsonValidationErrors('comment');

        $this->review($client, $booking, ['rating' => 4])->assertCreated(); // le commentaire est facultatif
    }

    public function test_only_the_traveller_of_the_stay_can_review(): void
    {
        $host = $this->makeUser('host');
        $client = $this->makeUser('client');
        $booking = $this->stay($client, $this->makeVilla($host));

        $this->review($this->makeUser('client'), $booking, ['rating' => 5])->assertForbidden();
        $this->review($host, $booking, ['rating' => 5])->assertForbidden();
    }

    public function test_the_average_covers_every_review_of_the_villa(): void
    {
        $villa = $this->makeVilla($this->makeUser('host'));
        $a = $this->makeUser('client');
        $b = $this->makeUser('client');

        $this->review($a, $this->stay($a, $villa), ['rating' => 5])->assertCreated();
        $this->review($b, $this->stay($b, $villa), ['rating' => 4])->assertCreated();

        $list = $this->getJson("/api/v1/properties/{$villa->id}/reviews")->assertOk()->assertJsonPath('count', 2)->assertJsonCount(2, 'items');
        $this->assertEquals(4.5, $list->json('average'));
    }

    public function test_the_booking_list_shows_the_rating_given(): void
    {
        $client = $this->makeUser('client');
        $booking = $this->stay($client, $this->makeVilla($this->makeUser('host')));
        $this->review($client, $booking, ['rating' => 3])->assertCreated();

        $this->actingAs($client, 'sanctum')->getJson('/api/v1/bookings')->assertOk()->assertJsonPath('data.0.review.rating', 3);
    }

    public function test_only_the_author_edits_a_review(): void
    {
        $client = $this->makeUser('client');
        $booking = $this->stay($client, $this->makeVilla($this->makeUser('host')));
        $id = $this->review($client, $booking, ['rating' => 5])->json('id');

        $this->actingAs($client, 'sanctum')->putJson("/api/v1/reviews/{$id}", ['rating' => 3, 'comment' => 'Finalement correct'])
            ->assertOk()->assertJsonPath('rating', 3)->assertJsonPath('comment', 'Finalement correct');
        $this->actingAs($this->makeUser('client'), 'sanctum')->putJson("/api/v1/reviews/{$id}", ['rating' => 1])->assertForbidden();
        $this->actingAs($this->makeUser('admin'), 'sanctum')->putJson("/api/v1/reviews/{$id}", ['rating' => 1])->assertForbidden();
    }

    public function test_author_or_admin_deletes_a_review_and_the_author_is_told_when_it_is_the_admin(): void
    {
        $admin = $this->makeUser('admin');
        $villa = $this->makeVilla($this->makeUser('host'));
        $author = $this->makeUser('client');
        $mine = $this->makeUser('client');

        $first = $this->review($author, $this->stay($author, $villa), ['rating' => 1, 'comment' => 'Avis abusif'])->json('id');
        $second = $this->review($mine, $this->stay($mine, $villa), ['rating' => 5])->json('id');

        $this->actingAs($this->makeUser('client'), 'sanctum')->deleteJson("/api/v1/reviews/{$first}")->assertForbidden();
        $this->actingAs($villa->owner, 'sanctum')->deleteJson("/api/v1/reviews/{$first}")->assertForbidden();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/reviews/{$first}")->assertNoContent();
        $this->assertSame(1, $author->notifications()->where('data', 'like', '%par un concierge%')->count());

        $this->actingAs($mine, 'sanctum')->deleteJson("/api/v1/reviews/{$second}")->assertNoContent();
        $this->assertSame(0, $mine->notifications()->count(), 'l\'auteur qui supprime lui-même n\'est pas notifié');
        $this->assertDatabaseCount('reviews', 0);
    }
}
