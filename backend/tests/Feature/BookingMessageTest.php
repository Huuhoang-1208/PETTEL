<?php

namespace Tests\Feature;

use App\Models\MediaObject;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participants_can_send_text_and_read_visible_messages_with_a_precise_cursor(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $url = "/api/bookings/{$booking->id}/messages";
        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00:00.200000', 'UTC'));

        $first = $this->postJson($url, ['body' => "  Ca\u{0301}  "])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Cá')
            ->assertJsonPath('data.sender_id', (string) $fixture['owner']->id)
            ->json('data.id');
        $second = $this->actingAs($fixture['caregiver'])->postJson($url, ['body' => 'Đã nhận'])
            ->assertCreated()
            ->assertJsonPath('data.sender_id', (string) $fixture['caregiver']->id)
            ->json('data.id');
        DB::table('messages')->insert([
            'booking_id' => $booking->id,
            'sender_id' => $fixture['owner']->id,
            'body' => 'Hidden message',
            'visibility' => 'HIDDEN',
            'created_at' => '2026-11-06 12:00:00.300000',
        ]);

        $page = $this->getJson($url.'?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.0.created_at', '2026-11-06T12:00:00.200000Z');
        $this->getJson($page->json('links.next'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first)
            ->assertJsonPath('links.next', null);

        $this->getJson($url.'?cursor=invalid')->assertUnprocessable();
        $this->postJson($url, ['body' => str_repeat('á', 2001)])->assertUnprocessable();
        $this->postJson($url, ['body' => 'Forged', 'sender_id' => $fixture['owner']->id])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_outsiders_receive_the_same_not_found_error_for_read_and_send(): void
    {
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/messages";
        $this->actingAs($fixture['foreign_pet']->owner);

        $this->getJson($url.'?per_page=invalid')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Booking not found.');
        $this->postJson($url, [])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->getJson('/api/bookings/0/messages')
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Booking not found.');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_completed_deadline_and_read_only_accounts_are_enforced(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $url = "/api/bookings/{$booking->id}/messages";
        $now = CarbonImmutable::parse('2026-11-06 12:00:00', 'UTC');
        $this->travelTo($now);
        $booking->update(['status' => 'COMPLETED']);
        DB::table('booking_status_events')->insert([
            'booking_id' => $booking->id,
            'from_status' => 'AWAITING_CONFIRMATION',
            'to_status' => 'COMPLETED',
            'source' => 'SYSTEM',
            'created_at' => $now->subDays(90)->addSecond(),
        ]);

        $this->postJson($url, ['body' => 'Còn lịch không?'])->assertCreated();
        $this->travelTo($now->addSecond());
        $this->postJson($url, ['body' => 'Outside the window'])
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $booking->update(['status' => 'CANCELLED']);
        $this->postJson($url, ['body' => 'Cancelled booking'])
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $fixture['owner']->forceFill(['status' => 'DELETION_PENDING'])->save();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($url, ['body' => 'Account pending deletion'])
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_both_participants_can_send_and_download_images_but_outsiders_and_hidden_messages_are_blocked(): void
    {
        $disk = Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $url = "/api/bookings/{$booking->id}/messages";
        $png = UploadedFile::fake()->image('photo.png', 12, 12);
        $jpeg = UploadedFile::fake()->image('photo.jpg', 12, 12);

        $sent = $this->post($url, ['attachments' => [$png, $jpeg]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.body', null)
            ->assertJsonCount(2, 'data.attachments')
            ->assertJsonPath('data.attachments.0.mime_type', 'image/png')
            ->assertJsonPath('data.attachments.1.mime_type', 'image/jpeg')
            ->assertJsonMissingPath('data.attachments.0.object_key')
            ->assertJsonMissingPath('data.attachments.0.sha256');
        $attachment = $sent->json('data.attachments.1');
        $media = MediaObject::query()->findOrFail($attachment['id']);
        $this->assertSame('PRIVATE', $media->visibility);
        $disk->assertExists($media->object_key);

        $this->actingAs($fixture['caregiver'])->getJson($url)->assertOk()
            ->assertJsonPath('data.0.attachments.1.url', $attachment['url']);
        $download = $this->get($attachment['url'])->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent(file_get_contents($jpeg->getPathname()));
        $this->assertTrue($download->headers->hasCacheControlDirective('no-store'));

        $reply = $this->post($url, [
            'body' => 'Đã nhận ảnh',
            'attachments' => [UploadedFile::fake()->image('reply.png', 12, 12)],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.body', 'Đã nhận ảnh');
        $replyUrl = $reply->json('data.attachments.0.url');
        $this->actingAs($fixture['owner'])->get($replyUrl)->assertOk();

        $other = BookingFixture::requested($this);
        $this->getJson($attachment['url'])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $otherMessageUrl = "/api/bookings/{$other['booking']->id}/messages/{$sent->json('data.id')}/attachment";
        $this->getJson($otherMessageUrl)->assertNotFound();
        $this->actingAs($fixture['owner'])->getJson($url."/{$sent->json('data.id')}/attachment/{$reply->json('data.attachments.0.id')}")
            ->assertNotFound();
        $this->get($url."/{$sent->json('data.id')}/attachment")->assertOk()->assertHeader('Content-Type', 'image/png');

        $booking->update(['status' => 'CANCELLED']);
        $this->post($url, ['attachments' => [$png]], ['Accept' => 'application/json'])->assertConflict();
        $fixture['owner']->forceFill(['status' => 'DELETION_PENDING'])->save();
        $this->get($attachment['url'])->assertOk();
        $this->post($url, ['attachments' => [$png]], ['Accept' => 'application/json'])->assertForbidden();

        DB::table('messages')->where('id', $sent->json('data.id'))->update(['visibility' => 'HIDDEN']);
        $this->getJson($attachment['url'])->assertNotFound();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        Auth::forgetGuards();
        $this->getJson($replyUrl)->assertUnauthorized();
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('media_objects', 3);
    }

    public function test_chat_media_cannot_be_reused_in_pets_listings_or_public_media(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/messages";
        $mediaId = $this->actingAs($fixture['caregiver'])->post($url, [
            'attachments' => [UploadedFile::fake()->image('chat.png', 12, 12)],
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.attachments.0.id');
        $items = [['media_object_id' => $mediaId, 'sort_order' => 1]];
        $activeListing = $fixture['active_listing_id'];

        $this->postJson('/api/pets', ['name' => 'New pet', 'species' => 'DOG', 'photo_media_id' => $mediaId])
            ->assertUnprocessable()->assertJsonValidationErrors('photo_media_id');
        $this->putJson("/api/caregiver/listings/{$fixture['draft_listing_id']}/media", ['media' => $items])
            ->assertUnprocessable();
        $this->postJson("/api/caregiver/listings/{$activeListing}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['media' => $items],
        ])->assertUnprocessable()->assertJsonPath('error.details.media.0', 'Images must be ready, owned by this caregiver, and unused by private content.');
        $this->getJson("/api/caregiver/media/{$mediaId}/content")->assertNotFound();
        $this->assertDatabaseCount('listing_media', 0);

        DB::table('listing_media')->insert(['listing_id' => $activeListing, 'media_object_id' => $mediaId, 'sort_order' => 1]);
        DB::table('media_objects')->where('id', $mediaId)->update(['visibility' => 'PUBLIC_ELIGIBLE']);
        $this->getJson("/api/listings/{$activeListing}/media/{$mediaId}/content")->assertNotFound();
        $this->getJson("/api/listings/{$activeListing}")->assertOk()->assertJsonCount(0, 'data.media');
    }

    public function test_images_validate_actual_content_count_and_combined_byte_limit(): void
    {
        $disk = Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/messages";
        $headers = ['Accept' => 'application/json'];
        $this->post($url, [
            'body' => 'Invalid image',
            'attachments' => [UploadedFile::fake()->createWithContent('photo.png', '<?php echo "executable";')],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post($url, ['attachments' => array_fill(0, 6, UploadedFile::fake()->image('photo.png', 12, 12))], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->postJson($url, ['attachments' => []])->assertUnprocessable()->assertJsonValidationErrors('body');

        $first = UploadedFile::fake()->image('first.png', 12, 12);
        $second = UploadedFile::fake()->image('second.png', 12, 12);
        foreach ([$first, $second] as $image) {
            file_put_contents($image->getPathname(), str_repeat("\0", 5_000_000 - filesize($image->getPathname())), FILE_APPEND);
            clearstatcache(true, $image->getPathname());
        }
        $this->post($url, ['attachments' => [$first, $second]], $headers)->assertCreated();
        file_put_contents($second->getPathname(), "\0", FILE_APPEND);
        clearstatcache(true, $second->getPathname());
        $this->post($url, ['attachments' => [$first, $second]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('media_objects', 2);
        $this->assertCount(2, $disk->allFiles());
    }

    public function test_storage_returning_false_cleans_all_attempted_images_and_creates_no_rows(): void
    {
        $disk = Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $mock = Mockery::mock();
        $mock->shouldReceive('putFileAs')->twice()->andReturnUsing(
            function (string $directory, UploadedFile $file, string $name, array $options) use ($disk): string|false {
                $path = $disk->putFileAs($directory, $file, $name, $options);

                return count($disk->allFiles()) === 1 ? $path : false;
            },
        );
        $mock->shouldReceive('delete')->twice()->andReturnUsing(fn (string $path): bool => $disk->delete($path));
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($mock);

        $this->post("/api/bookings/{$fixture['booking']->id}/messages", [
            'attachments' => [UploadedFile::fake()->image('first.png', 12, 12), UploadedFile::fake()->image('second.png', 12, 12)],
        ], ['Accept' => 'application/json'])->assertInternalServerError()->assertJsonPath('error.code', 'MEDIA_UPLOAD_FAILED');

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media_objects', 0);
        $this->assertDatabaseCount('message_attachments', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_second_media_insert_failure_rolls_back_the_message_and_cleans_stored_files(): void
    {
        $disk = Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        MediaObject::creating(function (): void {
            if (MediaObject::query()->exists()) {
                throw new RuntimeException('forced second media insert failure');
            }
        });

        $this->post("/api/bookings/{$fixture['booking']->id}/messages", [
            'body' => 'Private message',
            'attachments' => [UploadedFile::fake()->image('first.png', 12, 12), UploadedFile::fake()->image('second.png', 12, 12)],
        ], ['Accept' => 'application/json'])->assertInternalServerError()
            ->assertJsonPath('error.code', 'MEDIA_UPLOAD_FAILED')->assertDontSee('forced second media insert failure');

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('media_objects', 0);
        $this->assertDatabaseCount('message_attachments', 0);
        $this->assertSame([], $disk->allFiles());
    }
}
