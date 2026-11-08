<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Support\PublicReviewCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class PublicReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reviews_exclude_hidden_and_unfinished_but_label_demo(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $first = $this->addReview($booking, 5);
        $second = $this->addReview($booking, 4);
        $demo = $this->addReview($booking, 1, demo: true);
        $this->addReview($booking, 2, status: 'HIDDEN');
        $this->addReview($booking, 3, bookingStatus: 'CONFIRMED');
        $id = $booking->listing_id;

        $response = $this->getJson("/api/listings/{$id}/reviews")->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('rating_average', 4.5)
            ->assertJsonPath('review_count', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.is_demo', true);

        $this->assertSame(
            [(string) $demo, (string) $second, (string) $first],
            array_column($response->json('data'), 'id'),
        );
        $this->assertEqualsCanonicalizing(
            ['id', 'rating', 'body', 'is_demo', 'created_at'],
            array_keys($response->json('data.0')),
        );
        $this->assertStringNotContainsString('Hidden review', $response->getContent());
        $this->getJson("/api/listings/{$id}")->assertOk()
            ->assertJsonPath('data.rating_average', 4.5)
            ->assertJsonPath('data.review_count', 2);
        $this->getJson('/api/listings')->assertOk()
            ->assertJsonPath('data.0.rating_average', 4.5)
            ->assertJsonPath('data.0.review_count', 2);
    }

    public function test_no_reviews_returns_null_and_zero(): void
    {
        $fixture = BookingFixture::requested($this);
        $id = $fixture['booking']->listing_id;

        $this->getJson("/api/listings/{$id}/reviews")->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating_average', null)
            ->assertJsonPath('review_count', 0)
            ->assertJsonPath('meta.total', 0);
        $this->getJson("/api/listings/{$id}")->assertOk()
            ->assertJsonPath('data.rating_average', null)
            ->assertJsonPath('data.review_count', 0);
        $this->getJson('/api/listings')->assertOk()
            ->assertJsonPath('data.0.rating_average', null)
            ->assertJsonPath('data.0.review_count', 0);
    }

    public function test_only_demo_or_ineligible_reviews_do_not_produce_a_rating(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $this->addReview($booking, 5, demo: true);
        $this->addReview($booking, 1, status: 'HIDDEN');
        $this->addReview($booking, 2, bookingStatus: 'CONFIRMED');

        $this->getJson("/api/listings/{$booking->listing_id}/reviews")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_demo', true)
            ->assertJsonPath('rating_average', null)
            ->assertJsonPath('review_count', 0);
    }

    public function test_reads_reflect_creation_hiding_and_restoring(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $url = "/api/listings/{$booking->listing_id}";
        $reviewsUrl = $url.'/reviews';

        $this->getJson($url)->assertOk()->assertJsonPath('data.review_count', 0);
        $this->getJson($reviewsUrl)->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/bookings/{$booking->id}/reviews", ['rating' => 5])
            ->assertCreated();

        $this->getJson($reviewsUrl)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('rating_average', 5)
            ->assertJsonPath('review_count', 1);

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.rating_average', 5)
            ->assertJsonPath('data.review_count', 1);

        foreach (['HIDDEN', 'VISIBLE'] as $status) {
            DB::table('reviews')->where('booking_id', $booking->id)->update(['status' => $status]);
            PublicReviewCache::invalidate((string) $booking->listing_id);
            $visible = $status === 'VISIBLE';

            $this->getJson($url)->assertOk()
                ->assertJsonPath('data.rating_average', $visible ? 5 : null)
                ->assertJsonPath('data.review_count', $visible ? 1 : 0);

            $this->getJson($url.'/reviews')->assertOk()
                ->assertJsonCount($visible ? 1 : 0, 'data')
                ->assertJsonPath('rating_average', $visible ? 5 : null)
                ->assertJsonPath('review_count', $visible ? 1 : 0);
        }
    }

    public function test_pagination_keeps_full_rating_and_stable_order(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $this->addReview($booking, 1);
        $this->addReview($booking, 2);
        $latestReal = $this->addReview($booking, 2);
        $demo = $this->addReview($booking, 5, demo: true);
        $url = "/api/listings/{$booking->listing_id}/reviews";

        $this->getJson($url.'?per_page=1&page=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $demo)
            ->assertJsonPath('rating_average', 1.67)
            ->assertJsonPath('review_count', 3)
            ->assertJsonPath('meta.total', 4);

        $this->getJson($url.'?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.id', (string) $latestReal)
            ->assertJsonPath('rating_average', 1.67)
            ->assertJsonPath('review_count', 3);

        $this->getJson($url.'?page=99')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating_average', 1.67)
            ->assertJsonPath('review_count', 3);
    }

    public function test_ratings_are_scoped_to_each_listing_and_preserved_with_dates(): void
    {
        $first = BookingFixture::requested($this);
        $this->addReview($first['booking'], 5);
        $this->addReview($first['booking'], 4);
        $second = BookingFixture::requested($this);
        $this->addReview($second['booking'], 1);

        $this->getJson("/api/listings/{$second['booking']->listing_id}")->assertOk()
            ->assertJsonPath('data.rating_average', 1)
            ->assertJsonPath('data.review_count', 1);

        $filters = [
            'start_at' => $first['payload']['start_at'],
            'end_at' => $first['payload']['end_at'],
        ];
        $response = $this->getJson('/api/listings?'.http_build_query($filters))->assertOk();
        $items = collect($response->json('data'))->keyBy('id');
        $item = $items->get((string) $first['booking']->listing_id);

        $this->assertNotNull($item);
        $this->assertSame(4.5, $item['rating_average']);
        $this->assertSame(2, $item['review_count']);
        $this->assertSame(150000, $item['price_total_vnd']);

        $this->actingAs($first['owner'])
            ->getJson('/api/listings?'.http_build_query([
                ...$filters,
                'pet_id' => (string) $first['pet']->id,
            ]))->assertOk()->assertJsonPath('data.0.rating_average', 4.5);
    }

    public function test_public_review_route_respects_listing_visibility(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $this->addReview($booking, 5);
        $id = $booking->listing_id;
        $url = "/api/listings/{$id}/reviews";

        foreach (['DRAFT', 'PENDING_REVIEW', 'SUSPENDED', 'ARCHIVED'] as $status) {
            DB::table('listings')->where('id', $id)->update([
                'status' => $status,
                'suspension_source' => $status === 'SUSPENDED' ? 'ADMIN' : null,
            ]);
            $this->getJson($url)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }

        DB::table('listings')->where('id', $id)->update([
            'status' => 'ACTIVE',
            'suspension_source' => null,
        ]);
        $fixture['caregiver']->forceFill(['status' => 'DEACTIVATED'])->save();
        $this->getJson($url)->assertNotFound();

        $fixture['caregiver']->forceFill(['status' => 'ACTIVE'])->save();
        $fixture['caregiver']->caregiverProfile()->firstOrFail()->delete();
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/listings/0/reviews')->assertNotFound();
    }

    public function test_review_query_validation(): void
    {
        $fixture = BookingFixture::requested($this);
        $url = "/api/listings/{$fixture['booking']->listing_id}/reviews";

        foreach ([
            'per_page=0',
            'per_page=51',
            'page=0',
            'page=abc',
            'status=HIDDEN',
            'per_page[]=1',
            'page=9223372036854775807&per_page=50',
        ] as $query) {
            $this->getJson($url.'?'.$query)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    private function addReview(
        Booking $template,
        int $rating,
        bool $demo = false,
        string $status = 'VISIBLE',
        string $bookingStatus = 'COMPLETED',
    ): int {
        // Schema fixtures only; completion and moderation workflows are tested separately.
        $booking = $template->replicate();
        $booking->forceFill([
            'status' => $bookingStatus,
            'request_idempotency_key' => (string) Str::uuid(),
        ])->save();

        return DB::table('reviews')->insertGetId([
            'booking_id' => $booking->id,
            'owner_id' => $booking->owner_id,
            'listing_id' => $booking->listing_id,
            'rating' => $rating,
            'body' => $status === 'HIDDEN' ? 'Hidden review' : 'Public review',
            'status' => $status,
            'is_demo' => $demo,
            'created_at' => '2030-01-01 00:00:00.000000',
            'updated_at' => '2030-01-01 00:00:00.000000',
        ]);
    }
}
