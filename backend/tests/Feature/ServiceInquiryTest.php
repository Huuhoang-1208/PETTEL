<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ServiceInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_message_is_saved_without_a_booking_and_retries_do_not_duplicate_it(): void
    {
        $fixture = BookingFixture::make();
        $payload = ['listing_id' => $fixture['active_listing_id'], 'pet_id' => $fixture['pet']->id,
            'body' => 'Bé hơi nhát, bạn còn nhận bé không?', 'request_key' => (string) Str::uuid()];
        $id = $this->actingAs($fixture['owner'])->postJson('/api/inquiries', $payload)->assertCreated()->json('id');
        $this->postJson('/api/inquiries', $payload)->assertCreated()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('service_inquiries', 1);
        $this->assertDatabaseCount('inquiry_messages', 1);
        $this->assertDatabaseCount('bookings', 0);
        $this->getJson("/api/inquiries/{$id}/messages")->assertOk()->assertJsonPath('data.0.body', $payload['body'])
            ->assertJsonPath('data.0.context.pet_name', $fixture['pet']->name);
        $this->postJson('/api/inquiries', [...$payload, 'body' => 'Nội dung khác'])->assertConflict();
    }

    public function test_only_participants_can_read_and_reply(): void
    {
        $fixture = BookingFixture::make();
        $id = $this->actingAs($fixture['owner'])->postJson('/api/inquiries', [
            'listing_id' => $fixture['active_listing_id'], 'body' => 'Chào bạn', 'request_key' => (string) Str::uuid(),
        ])->assertCreated()->json('id');
        $this->actingAs($fixture['caregiver'])->getJson('/api/inquiries')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/inquiries/{$id}/messages")->assertOk();
        $this->postJson("/api/inquiries/{$id}/messages", ['body' => 'Mình còn nhận bé nhé', 'request_key' => (string) Str::uuid()])->assertCreated();
        $this->actingAs($fixture['foreign_pet']->owner)->getJson("/api/inquiries/{$id}")->assertNotFound();
        $this->getJson("/api/inquiries/{$id}/messages")->assertNotFound();
        $this->postJson("/api/inquiries/{$id}/messages", ['body' => 'Không được gửi', 'request_key' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_pet_must_belong_to_sender_and_listing_must_be_public(): void
    {
        $fixture = BookingFixture::make();
        $payload = ['listing_id' => $fixture['active_listing_id'], 'body' => 'Tư vấn giúp mình', 'request_key' => (string) Str::uuid()];
        $this->actingAs($fixture['owner'])->postJson('/api/inquiries', [...$payload, 'pet_id' => $fixture['foreign_pet']->id])->assertNotFound();
        $this->postJson('/api/inquiries', [...$payload, 'listing_id' => $fixture['draft_listing_id']])->assertNotFound();
        $this->postJson('/api/inquiries', [...$payload, 'body' => '   '])->assertUnprocessable();
        $this->assertDatabaseCount('service_inquiries', 0);
    }
}
