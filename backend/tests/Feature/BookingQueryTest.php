<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_participants_can_discover_or_read_a_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $id = (string) $fixture['booking']->id;
        $this->actingAs($fixture['owner'])->getJson('/api/v1/bookings')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.participant_role', 'OWNER');
        $this->getJson("/api/v1/bookings/{$id}")->assertOk()->assertJsonPath('data.pet_name', 'Mochi');
        $this->actingAs($fixture['caregiver'], 'web')->getJson("/api/v1/bookings/{$id}")->assertOk()
            ->assertJsonPath('data.participant_role', 'CAREGIVER');
        $otherOwner = User::query()->findOrFail($fixture['foreign_pet']->user_id);
        $this->actingAs($otherOwner, 'web')->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/bookings/{$id}")->assertNotFound();
    }

    public function test_redacted_snapshots_and_internal_keys_are_not_exposed(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->privacy_redacted_at = now();
        $booking->save();
        $this->actingAs($fixture['owner'])->getJson("/api/v1/bookings/{$booking->id}")->assertOk()
            ->assertJsonPath('data.listing_title', null)->assertJsonPath('data.pet_name', null)
            ->assertJsonMissingPath('data.request_idempotency_key')->assertJsonMissingPath('data.request_payload_hash');
    }

    public function test_guest_cannot_read_bookings(): void
    {
        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->getJson('/api/v1/bookings/1')->assertUnauthorized();
    }
}
