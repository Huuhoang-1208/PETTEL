<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_sets_one_hold_and_event_without_changing_snapshots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01T00:00:00.123456Z'));
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $due = CarbonImmutable::now('UTC')->addMinutes(15)->format('Y-m-d\TH:i:s.u\Z');
        $url = "/api/bookings/{$booking->id}/accept";

        $this->actingAs($fixture['caregiver'])->postJson($url)
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT')
            ->assertJsonPath('payment_due_at', $due);
        $after = $booking->fresh()->getAttributes();
        foreach ($before as $field => $value) {
            if (! in_array($field, ['status', 'version', 'updated_at', 'payment_due_at'], true)) {
                $this->assertSame($value, $after[$field], $field);
            }
        }
        $this->assertSame($before['version'] + 1, $after['version']);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'AWAITING_PAYMENT',
            'actor_user_id' => $fixture['caregiver']->id,
            'source' => 'USER',
        ]);

        $this->postJson($url)->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertSame($after, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_route_checks_auth_ownership_and_listing_state(): void
    {
        $this->postJson('/api/bookings/0/accept')->assertUnauthorized();
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/accept";
        $this->actingAs($fixture['owner'])->postJson($url)->assertForbidden();
        $other = BookingFixture::make()['caregiver'];
        $this->actingAs($other)->postJson($url)->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
        DB::table('listings')->where('id', $fixture['active_listing_id'])
            ->update(['status' => 'SUSPENDED']);
        $this->actingAs($fixture['caregiver'])->postJson($url)->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertSame('REQUESTED', $fixture['booking']->fresh()->status);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_event_failure_rolls_back_booking_and_payment_deadline(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `booking_status_events`')) {
                throw new RuntimeException('Simulated accept event failure');
            }
        });

        try {
            $this->actingAs($fixture['caregiver'])
                ->postJson("/api/bookings/{$booking->id}/accept");
            $this->fail('Expected event failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated accept event failure', $exception->getMessage());
        }
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_expiry_is_checked_again_after_calendar_reads(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $deadline = $booking->created_at->addHours(24);
        DB::listen(function (QueryExecuted $query) use ($deadline): void {
            if (str_contains($query->sql, 'from `bookings`') && str_contains($query->sql, '`status` in')) {
                $this->travelTo($deadline);
            }
        });

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'REQUEST_EXPIRED');
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }
}
