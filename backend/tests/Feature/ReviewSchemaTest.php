<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReviewSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_ratings_are_stored_with_defaults(): void
    {
        foreach ([1, 5] as $rating) {
            $data = $this->reviewData();
            $data['rating'] = $rating;
            $data['body'] = $rating === 1 ? null : 'Review schema test';

            DB::table('reviews')->insert($data);

            $this->assertDatabaseHas('reviews', [
                'booking_id' => $data['booking_id'],
                'owner_id' => $data['owner_id'],
                'listing_id' => $data['listing_id'],
                'rating' => $rating,
                'body' => $data['body'],
                'status' => 'VISIBLE',
                'is_demo' => false,
            ]);
        }

        $this->assertDatabaseCount('reviews', 2);
    }

    #[DataProvider('invalidRatings')]
    public function test_database_rejects_out_of_range_rating(int $rating): void
    {
        $data = $this->reviewData();
        $data['rating'] = $rating;

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_review_rating');

        DB::table('reviews')->insert($data);
    }

    public static function invalidRatings(): array
    {
        return [
            'below minimum' => [0],
            'above maximum' => [6],
        ];
    }

    #[DataProvider('reviewStatuses')]
    public function test_booking_cannot_have_a_second_review(string $status): void
    {
        $data = $this->reviewData();
        DB::table('reviews')->insert([...$data, 'status' => $status]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('reviews')->insert([...$data, 'rating' => 4]);
    }

    public static function reviewStatuses(): array
    {
        return [
            'visible review' => ['VISIBLE'],
            'hidden review' => ['HIDDEN'],
        ];
    }

    public function test_database_rejects_unknown_review_status(): void
    {
        $data = $this->reviewData();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_review_status');

        DB::table('reviews')->insert([...$data, 'status' => 'PENDING']);
    }

    /** @return array<string, mixed> */
    private function reviewData(): array
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];

        // Schema fixture only; this does not exercise completion or release.
        $booking->update(['status' => 'COMPLETED']);

        return [
            'booking_id' => $booking->id,
            'owner_id' => $booking->owner_id,
            'listing_id' => $booking->listing_id,
            'rating' => 5,
            'body' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
