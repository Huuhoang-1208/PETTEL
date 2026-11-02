<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ValidateBookingAcceptance;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceConditionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_passes_without_changing_booking_or_events(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();

        $validated = app(ValidateBookingAcceptance::class)
            ->handle($fixture['caregiver'], (string) $booking->id);

        $this->assertSame($booking->id, $validated->id);
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_wrong_caregiver_and_missing_booking_return_not_found(): void
    {
        $fixture = BookingFixture::requested($this);
        $other = BookingFixture::make()['caregiver'];

        $this->assertRejected($other, (string) $fixture['booking']->id, 'NOT_FOUND', 404);
        $this->assertRejected($fixture['caregiver'], '0', 'NOT_FOUND', 404);
    }

    public function test_inactive_caregiver_cannot_pass_validation(): void
    {
        $fixture = BookingFixture::requested($this);
        $fixture['caregiver']->forceFill(['status' => 'DEACTIVATED'])->save();

        try {
            app(ValidateBookingAcceptance::class)
                ->handle($fixture['caregiver'], (string) $fixture['booking']->id);
            $this->fail('Expected forbidden response.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_only_requested_bookings_can_pass_validation(): void
    {
        $fixture = BookingFixture::requested($this);
        foreach (['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED'] as $status) {
            $fixture['booking']->update(['status' => $status]);
            $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');
        }
    }

    public function test_request_expiry_is_enforced_at_both_boundaries(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];

        $deadlines = [$booking->created_at->addHours(24), $booking->start_at];
        foreach ($deadlines as $index => $deadline) {
            if ($index === 1) {
                $booking->forceFill(['created_at' => $deadline->subHour()])->save();
            }
            $this->travelTo($deadline->subMicrosecond());
            $validated = app(ValidateBookingAcceptance::class)
                ->handle($fixture['caregiver'], (string) $booking->id);
            $this->assertSame('REQUESTED', $validated->status);

            $this->travelTo($deadline);
            $this->assertRejected($fixture['caregiver'], (string) $booking->id, 'REQUEST_EXPIRED');
            $this->assertSame('REQUESTED', $booking->fresh()->status);
        }
    }

    public function test_listing_must_be_active_and_not_soft_deleted(): void
    {
        $fixture = BookingFixture::requested($this);
        $listing = DB::table('listings')->where('id', $fixture['active_listing_id']);

        foreach (['DRAFT', 'PENDING_REVIEW', 'SUSPENDED', 'ARCHIVED'] as $status) {
            $listing->update(['status' => $status]);
            $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');
        }

        $listing->update(['status' => 'ACTIVE', 'deleted_at' => now()]);
        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');
    }

    public function test_deleted_profile_prevents_acceptance(): void
    {
        $fixture = BookingFixture::requested($this);
        $fixture['caregiver']->caregiverProfile()->firstOrFail()->delete();

        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');
    }

    public function test_overlapping_requests_pass_but_blocking_statuses_fail(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'accept-conditions-second')
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $other = Booking::query()->findOrFail($response->json('id'));

        foreach (['REQUESTED', 'COMPLETED', 'CANCELLED'] as $status) {
            $other->update(['status' => $status]);
            $validated = app(ValidateBookingAcceptance::class)
                ->handle($fixture['caregiver'], (string) $booking->id);
            $this->assertSame('REQUESTED', $validated->status);
        }

        foreach (['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'] as $status) {
            $other->update(['status' => $status, 'payment_due_at' => now()->subMinute()]);
            $this->assertRejected($fixture['caregiver'], (string) $booking->id, 'AVAILABILITY_CONFLICT');
        }

        $other->update([
            'status' => 'CONFIRMED',
            'start_at' => $booking->start_at->subHour(),
            'end_at' => $booking->start_at,
        ]);
        $validated = app(ValidateBookingAcceptance::class)
            ->handle($fixture['caregiver'], (string) $booking->id);
        $this->assertSame('REQUESTED', $validated->status);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_no_slot_or_partial_slot_is_not_enough(): void
    {
        $fixture = BookingFixture::requested($this);
        $slots = DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id']);
        $slots->update(['deleted_at' => now()]);
        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'AVAILABILITY_CONFLICT');

        $slots->update(['deleted_at' => null, 'end_at' => $fixture['booking']->end_at->subMinute()]);
        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'AVAILABILITY_CONFLICT');
    }

    public function test_price_change_keeps_the_original_snapshot(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        DB::table('listings')->where('id', $fixture['active_listing_id'])
            ->update(['price_vnd' => 70000, 'version' => 2]);

        app(ValidateBookingAcceptance::class)->handle($fixture['caregiver'], (string) $booking->id);
        $this->assertSame($before, $booking->fresh()->getAttributes());
    }

    public function test_service_or_acceptance_rules_change_requires_a_new_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $listing = DB::table('listings')->where('id', $fixture['active_listing_id']);
        $listing->update(['service_type' => 'BOARDING', 'price_unit' => 'NIGHT']);
        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');

        $listing->update([
            'service_type' => 'DAYCARE',
            'price_unit' => 'HOUR',
            'acceptance_rules_json' => json_encode([
                'schema_version' => 1,
                'species' => ['CAT'],
                'sizes' => ['SMALL'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $this->assertRejected($fixture['caregiver'], (string) $fixture['booking']->id, 'INVALID_STATE');
    }

    private function assertRejected(User $caregiver, string $id, string $code, int $status = 409): void
    {
        try {
            app(ValidateBookingAcceptance::class)->handle($caregiver, $id);
            $this->fail('Expected acceptance validation to fail.');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $this->assertSame($status, $response->getStatusCode());
            $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($code, $body['error']['code']);
        }
    }
}
