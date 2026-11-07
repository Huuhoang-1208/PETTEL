<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReviewCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_booking_owner_can_create_a_review(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);

        $this->postJson("/api/bookings/{$booking->id}/reviews", [
            'rating' => 5,
            'body' => "  Cha\u{0306}m sóc tốt  ",
        ])->assertCreated()
            ->assertJsonPath('data.booking_id', (string) $booking->id)
            ->assertJsonPath('data.listing_id', (string) $booking->listing_id)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.body', 'Chăm sóc tốt')
            ->assertJsonPath('data.status', 'VISIBLE')
            ->assertJsonPath('data.is_demo', false);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'owner_id' => $fixture['owner']->id,
            'listing_id' => $booking->listing_id,
            'rating' => 5,
            'body' => 'Chăm sóc tốt',
        ]);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_body_is_optional_and_rating_one_is_accepted(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);

        $this->postJson("/api/bookings/{$booking->id}/reviews", ['rating' => 1])
            ->assertCreated()->assertJsonPath('data.body', null)
            ->assertJsonPath('data.rating', 1);
    }

    public function test_login_is_required(): void
    {
        $this->postJson('/api/bookings/1/reviews', ['rating' => 5])
            ->assertUnauthorized();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_wrong_owner_and_missing_booking_return_not_found(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $otherOwner = BookingFixture::make()['owner'];
        $this->actingAs($otherOwner);

        foreach ([$booking->id, 0] as $id) {
            $this->postJson("/api/bookings/{$id}/reviews", ['rating' => 5])
                ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_active_owner_role_is_required(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $url = "/api/bookings/{$booking->id}/reviews";

        foreach (['DEACTIVATED', 'DELETION_PENDING'] as $status) {
            $fixture['owner']->forceFill(['status' => $status])->save();
            $this->actingAs($fixture['owner'])->postJson($url, ['rating' => 5])
                ->assertForbidden();
        }

        $fixture['owner']->forceFill(['status' => 'ACTIVE'])->save();

        DB::table('user_roles')->where('user_id', $fixture['owner']->id)
            ->where('role', 'OWNER')->delete();

        $this->actingAs($fixture['owner'])->postJson($url, ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_all_unfinished_or_cancelled_states_are_rejected(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];

        foreach ([
            'REQUESTED',
            'AWAITING_PAYMENT',
            'CONFIRMED',
            'IN_PROGRESS',
            'AWAITING_CONFIRMATION',
            'CANCELLED',
        ] as $status) {
            $booking->update(['status' => $status]);

            $this->postJson("/api/bookings/{$booking->id}/reviews", ['rating' => 5])
                ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_invalid_payloads_are_rejected(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $url = "/api/bookings/{$booking->id}/reviews";

        foreach ([
            [],
            ['rating' => 0],
            ['rating' => 6],
            ['rating' => 1.5],
            ['rating' => true],
            ['rating' => '5'],
            ['rating' => 5, 'body' => str_repeat('a', 1001)],
            ['rating' => 5, 'body' => ['invalid']],
        ] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        foreach (['owner_id', 'listing_id', 'status', 'is_demo'] as $field) {
            $this->postJson($url, ['rating' => 5, $field => null])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['error' => ['details' => [$field]]]);
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_duplicate_review_is_rejected_even_when_hidden(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $url = "/api/bookings/{$booking->id}/reviews";

        $this->postJson($url, ['rating' => 3])->assertCreated();

        foreach (['VISIBLE', 'HIDDEN'] as $status) {
            DB::table('reviews')->where('booking_id', $booking->id)
                ->update(['status' => $status]);

            $this->postJson($url, ['rating' => 5])
                ->assertConflict()
                ->assertJsonPath('error.code', 'REVIEW_ALREADY_EXISTS');
        }

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'rating' => 3,
        ]);
    }
}
