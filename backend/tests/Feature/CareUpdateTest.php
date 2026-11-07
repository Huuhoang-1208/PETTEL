<?php

namespace Tests\Feature;

use App\Models\CareUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class CareUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_create_text_and_image_update_only_while_booking_is_in_progress(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'IN_PROGRESS']);
        $url = "/api/bookings/{$booking->id}/care-updates";

        $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => 'Walk completed.'])
            ->assertCreated()->assertJsonPath('data.booking_id', (string) $booking->id)
            ->assertJsonPath('data.sender.id', (string) $fixture['caregiver']->id)
            ->assertJsonPath('data.body', 'Walk completed.')
            ->assertJsonPath('data.image_url', null)
            ->assertJsonMissingPath('data.object_key')
            ->assertJsonMissingPath('data.sha256');

        $image = UploadedFile::fake()->image('care.png', 32, 24)->mimeType('image/png');
        $response = $this->actingAs($fixture['caregiver'])->post($url, ['image' => $image], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('data.body', null);
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertDatabaseCount('care_updates', 2);
        $this->assertDatabaseCount('media_objects', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());

        foreach (['REQUESTED', 'AWAITING_PAYMENT', 'CONFIRMED', 'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED'] as $state) {
            $booking->update(['status' => $state]);
            $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => 'Not allowed.'])
                ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        }
        $this->assertDatabaseCount('care_updates', 2);
    }

    public function test_only_booking_participants_can_read_updates_and_private_images(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'IN_PROGRESS']);
        $url = "/api/bookings/{$booking->id}/care-updates";
        $response = $this->actingAs($fixture['caregiver'])->post($url, [
            'body' => 'Photo update',
            'image' => UploadedFile::fake()->image('care.jpg', 32, 24),
        ], ['Accept' => 'application/json'])->assertCreated();
        $imageUrl = $response->json('data.image_url');
        $imagePath = parse_url($imageUrl, PHP_URL_PATH).'?'.parse_url($imageUrl, PHP_URL_QUERY);

        $this->actingAs($fixture['owner'])->getJson($url)->assertOk()->assertJsonPath('data.0.body', 'Photo update');
        $this->actingAs($fixture['caregiver'])->getJson($imagePath)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $otherOwner = BookingFixture::make()['owner'];
        $this->actingAs($otherOwner)->getJson($url)->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->actingAs($otherOwner)->get($imagePath)->assertForbidden();
        $this->actingAs($fixture['owner'])->get($imagePath.'&signature=invalid')->assertForbidden();
    }

    public function test_update_validation_and_upload_failure_do_not_create_partial_records(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'IN_PROGRESS']);
        $url = "/api/bookings/{$booking->id}/care-updates";

        $this->actingAs($fixture['caregiver'])->postJson($url, [])->assertUnprocessable();
        $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => '   '])->assertUnprocessable();
        $this->actingAs($fixture['caregiver'])->post($url, [
            'image' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('care_updates', 0);
        $this->assertDatabaseCount('media_objects', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_storage_upload_failure_does_not_create_update_or_media(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'IN_PROGRESS']);
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('exists')->once()->andReturn(true);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->times(3)->with('local')->andReturn($disk);
        Log::spy();

        $this->actingAs($fixture['caregiver'])->post(
            "/api/bookings/{$booking->id}/care-updates",
            ['image' => UploadedFile::fake()->image('failed.png', 30, 20)],
            ['Accept' => 'application/json'],
        )->assertInternalServerError()->assertJsonPath('error.code', 'MEDIA_UPLOAD_FAILED');

        $this->assertDatabaseCount('care_updates', 0);
        $this->assertDatabaseCount('media_objects', 0);
    }

    public function test_care_update_timeline_is_stably_ordered_and_link_expires(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'IN_PROGRESS']);
        $url = "/api/bookings/{$booking->id}/care-updates";
        $first = $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => 'first'])->assertCreated();
        $second = $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => 'second'])->assertCreated();

        DB::table('care_updates')->where('id', $second->json('data.id'))->update([
            'created_at' => DB::table('care_updates')->where('id', $first->json('data.id'))->value('created_at'),
        ]);
        $this->actingAs($fixture['owner'])->getJson($url)->assertOk()
            ->assertJsonPath('data.0.body', 'first')->assertJsonPath('data.1.body', 'second');

        $photo = $this->actingAs($fixture['caregiver'])->post($url, [
            'image' => UploadedFile::fake()->image('care.webp', 20, 20),
        ], ['Accept' => 'application/json'])->assertCreated();
        $imageUrl = $photo->json('data.image_url');
        $imagePath = parse_url($imageUrl, PHP_URL_PATH).'?'.parse_url($imageUrl, PHP_URL_QUERY);
        $this->travel(6)->minutes();
        $this->actingAs($fixture['caregiver'])->get($imagePath)->assertForbidden();
    }

    public function test_retention_redacts_expired_updates_and_honors_open_reports(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $holdFixture = BookingFixture::requested($this);
        $recentlyClosedFixture = BookingFixture::requested($this);
        $media = DB::table('media_objects')->insertGetId([
            'owner_user_id' => $fixture['caregiver']->id,
            'object_key' => 'care-updates/'.$booking->id.'/expired.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'test'),
            'visibility' => 'PRIVATE',
            'state' => 'READY',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);
        Storage::disk('local')->put('care-updates/'.$booking->id.'/expired.jpg', 'test');
        $messageFixture = BookingFixture::requested($this);
        $sharedMedia = DB::table('media_objects')->insertGetId([
            'owner_user_id' => $messageFixture['caregiver']->id,
            'object_key' => 'care-updates/'.$messageFixture['booking']->id.'/message-shared.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'shared'),
            'visibility' => 'PRIVATE',
            'state' => 'READY',
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);
        Storage::disk('local')->put('care-updates/'.$messageFixture['booking']->id.'/message-shared.jpg', 'shared');
        $expiredId = DB::table('care_updates')->insertGetId([
            'booking_id' => $booking->id,
            'sender_id' => $fixture['caregiver']->id,
            'body' => 'Old private note',
            'media_object_id' => $media,
            'visibility' => 'VISIBLE',
            'created_at' => now()->subDays(100),
        ]);
        $sharedUpdateId = DB::table('care_updates')->insertGetId([
            'booking_id' => $messageFixture['booking']->id,
            'sender_id' => $messageFixture['caregiver']->id,
            'body' => 'Old note with message image reference',
            'media_object_id' => $sharedMedia,
            'visibility' => 'VISIBLE',
            'created_at' => now()->subDays(100),
        ]);
        $messageId = DB::table('messages')->insertGetId([
            'booking_id' => $messageFixture['booking']->id,
            'sender_id' => $messageFixture['caregiver']->id,
            'body' => 'Image is also attached to a message',
            'visibility' => 'VISIBLE',
            'created_at' => now()->subDays(100),
        ]);
        DB::table('message_attachments')->insert([
            'message_id' => $messageId,
            'media_object_id' => $sharedMedia,
        ]);
        $heldId = DB::table('care_updates')->insertGetId([
            'booking_id' => $holdFixture['booking']->id,
            'sender_id' => $holdFixture['caregiver']->id,
            'body' => 'Held note',
            'visibility' => 'VISIBLE',
            'created_at' => now()->subDays(100),
        ]);
        $recentlyClosedId = DB::table('care_updates')->insertGetId([
            'booking_id' => $recentlyClosedFixture['booking']->id,
            'sender_id' => $recentlyClosedFixture['caregiver']->id,
            'body' => 'Recently retained note',
            'visibility' => 'VISIBLE',
            'created_at' => now()->subDays(100),
        ]);
        DB::table('reports')->insert([
            'reporter_user_id' => $holdFixture['owner']->id,
            'booking_id' => $holdFixture['booking']->id,
            'reason_code' => 'SAFETY_CONCERN',
            'status' => 'OPEN',
            'created_at' => now()->subDays(95),
            'updated_at' => now()->subDays(95),
        ]);
        DB::table('reports')->insert([
            'reporter_user_id' => $recentlyClosedFixture['owner']->id,
            'booking_id' => $recentlyClosedFixture['booking']->id,
            'reason_code' => 'SAFETY_CONCERN',
            'status' => 'RESOLVED',
            'resolved_at' => now()->subDays(60),
            'created_at' => now()->subDays(95),
            'updated_at' => now()->subDays(60),
        ]);

        $this->artisan('care-updates:redact-expired')->assertSuccessful();
        $this->assertDatabaseHas('care_updates', ['id' => $expiredId, 'body' => null, 'media_object_id' => null]);
        $this->assertDatabaseHas('care_updates', ['id' => $sharedUpdateId, 'body' => null, 'media_object_id' => null]);
        $this->assertNotNull(CareUpdate::query()->findOrFail($expiredId)->redacted_at);
        $this->assertDatabaseHas('care_updates', ['id' => $heldId, 'body' => 'Held note', 'redacted_at' => null]);
        $this->assertDatabaseHas('care_updates', [
            'id' => $recentlyClosedId, 'body' => 'Recently retained note', 'redacted_at' => null,
        ]);
        $this->assertDatabaseMissing('media_objects', ['id' => $media]);
        $this->assertDatabaseHas('media_objects', ['id' => $sharedMedia, 'state' => 'READY']);
        $this->assertSame(
            ['care-updates/'.$messageFixture['booking']->id.'/message-shared.jpg'],
            Storage::disk('local')->allFiles(),
        );
    }
}
