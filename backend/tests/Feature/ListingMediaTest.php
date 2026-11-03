<?php

namespace Tests\Feature;

use App\Domain\Listing\Actions\ListingWorkflow;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class ListingMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_jpeg_png_and_webp_uploads_are_private_normalized_and_do_not_expose_storage_metadata(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $forgedObjectKey = 'public/attacker.jpg';
        $forgedHash = str_repeat('f', 64);
        $this->postImage($caregiver, $this->image('png'), [
            'owner_user_id' => 999999,
            'object_key' => $forgedObjectKey,
            'sha256' => $forgedHash,
            'visibility' => 'PUBLIC_ELIGIBLE',
            'state' => 'PENDING_DELETE',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('media_objects', 0);

        foreach ([
            ['jpeg', 'image/jpeg'],
            ['png', 'image/png'],
            ['webp', 'image/webp'],
        ] as [$format, $mime]) {
            $response = $this->postImage($caregiver, $this->image($format));
            $response->assertCreated()
                ->assertJsonPath('data.mime_type', $mime)
                ->assertJsonMissingPath('data.object_key')
                ->assertJsonMissingPath('data.sha256')
                ->assertJsonMissingPath('data.owner_user_id')
                ->assertJsonMissingPath('data.state')
                ->assertJsonMissingPath('data.visibility');

            $media = MediaObject::query()->findOrFail($response->json('data.id'));
            $this->assertSame((int) $caregiver->id, (int) $media->owner_user_id);
            $this->assertSame('PRIVATE', $media->visibility);
            $this->assertSame('READY', $media->state);
            $this->assertNotSame($forgedObjectKey, $media->object_key);
            $this->assertNotSame($forgedHash, $media->sha256);
            $this->assertGreaterThan(0, $media->byte_size);
            $this->assertStringNotContainsString($media->object_key, $response->getContent());
            $this->assertStringNotContainsString($media->sha256, $response->getContent());

            $disk = Storage::disk('local');
            $thumbnailKey = $this->thumbnailKey($media->object_key);
            $disk->assertExists($media->object_key);
            $disk->assertExists($thumbnailKey);
            $this->assertSame($mime, getimagesize($disk->path($media->object_key))['mime']);
            $this->assertSame('image/webp', getimagesize($disk->path($thumbnailKey))['mime']);

            $this->actingAs($caregiver)->getJson("/api/caregiver/media/{$media->id}/content")
                ->assertOk()->assertHeader('Content-Type', $mime);
            $this->actingAs($caregiver)->getJson("/api/caregiver/media/{$media->id}/content?variant=thumbnail")
                ->assertOk()->assertHeader('Content-Type', 'image/webp');
        }

        $this->assertDatabaseCount('media_objects', 3);
    }

    public function test_upload_enforces_exact_byte_limit_pixel_limit_and_content_mime(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];

        $atLimit = $this->image('png');
        $limit = 10 * 1024 * 1024;
        $this->assertLessThan($limit, filesize($atLimit->getPathname()));
        file_put_contents($atLimit->getPathname(), str_repeat("\0", $limit - filesize($atLimit->getPathname())), FILE_APPEND);
        $this->postImage($caregiver, $atLimit)->assertCreated();

        $tooLarge = $this->image('png');
        file_put_contents($tooLarge->getPathname(), str_repeat("\0", $limit + 1 - filesize($tooLarge->getPathname())), FILE_APPEND);
        $this->postImage($caregiver, $tooLarge)->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        foreach ([
            UploadedFile::fake()->createWithContent('fake.jpg', 'this is not an image'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            UploadedFile::fake()->createWithContent('payload.png', '<?php echo "executable";'),
        ] as $invalidFile) {
            $this->postImage($caregiver, $invalidFile)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->postImage($caregiver, $this->oversizedPng(5001, 4000))->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('media_objects', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_media_endpoints_reject_unknown_request_fields_and_query_parameters(): void
    {
        Storage::fake('local');
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['draft_listing_id'];
        $media = $this->uploadedMedia($caregiver);

        $this->postImage($caregiver, $this->image('png'), ['unexpected' => 'value'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->actingAs($caregiver)->putJson("/api/caregiver/listings/{$listingId}/media", ['media' => [], 'unexpected' => true])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->putJson("/api/caregiver/listings/{$listingId}/media?unexpected=1", ['media' => []])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->putJson("/api/caregiver/listings/{$listingId}/media", ['media' => [['media_object_id' => (string) $media->id, 'sort_order' => 1]]])
            ->assertOk();
        $this->get("/api/caregiver/listings/{$listingId}/media/{$media->id}/content?unexpected=1")
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->get("/api/caregiver/listings/{$listingId}/media/{$media->id}/content?variant=small")
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseHas('listing_media', ['listing_id' => $listingId, 'media_object_id' => $media->id, 'sort_order' => 1]);
    }

    public function test_database_failure_cleans_both_upload_files_and_returns_a_generic_error(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        MediaObject::creating(function (): never {
            throw new RuntimeException('forced media row insert failure');
        });

        $response = $this->postImage($fixture['caregiver'], $this->image('png'));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('error.code', 'MEDIA_UPLOAD_FAILED')
            ->assertJsonMissingPath('error.details.object_key')
            ->assertJsonMissingPath('error.details.sha256');

        $this->assertDatabaseCount('media_objects', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cleanup_failure_logs_retryable_keys_without_exposing_them_in_the_response(): void
    {
        $fixture = BookingFixture::make();
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->twice()->andReturn(true, false);
        $disk->shouldReceive('delete')->twice()->andReturn(false, true);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);
        Log::spy();

        $response = $this->postImage($fixture['caregiver'], $this->image('png'));
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJsonPath('error.code', 'MEDIA_UPLOAD_FAILED')
            ->assertDontSee('object_key')
            ->assertDontSee('sha256');

        Log::shouldHaveReceived('error')->with(
            'listing.media.upload_cleanup_failed',
            Mockery::on(fn (array $context): bool => (int) $context['owner_user_id'] === (int) $fixture['caregiver']->id
                && count($context['object_keys']) === 1
                && str_starts_with($context['object_keys'][0], 'listings/'.$fixture['caregiver']->id.'/')
                && $context['upload_exception'] === RuntimeException::class
            ),
        )->once();
        $this->assertDatabaseCount('media_objects', 0);
    }

    public function test_draft_media_can_swap_order_and_detach_without_deleting_retained_files(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['draft_listing_id'];
        $first = $this->uploadedMedia($caregiver);
        $second = $this->uploadedMedia($caregiver);
        $url = "/api/caregiver/listings/{$listingId}/media";

        $this->actingAs($caregiver)->putJson($url, ['media' => [
            ['media_object_id' => (string) $first->id, 'sort_order' => 1],
            ['media_object_id' => (string) $second->id, 'sort_order' => 2],
        ]])->assertOk()->assertJsonPath('data.media.0.media_object_id', (string) $first->id);

        $this->putJson($url, ['media' => [
            ['media_object_id' => (string) $second->id, 'sort_order' => 1],
            ['media_object_id' => (string) $first->id, 'sort_order' => 2],
        ]])->assertOk()->assertJsonPath('data.media.0.media_object_id', (string) $second->id);

        $this->putJson($url, ['media' => []])->assertOk()->assertJsonPath('data.media', []);
        $this->assertDatabaseCount('listing_media', 0);
        foreach ([$first, $second] as $media) {
            $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'state' => 'READY']);
            Storage::disk('local')->assertExists($media->object_key);
            Storage::disk('local')->assertExists($this->thumbnailKey($media->object_key));
        }
    }

    public function test_draft_media_rejects_duplicates_foreign_or_referenced_media_and_noncontiguous_order(): void
    {
        Storage::fake('local');
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $url = "/api/caregiver/listings/{$fixture['draft_listing_id']}/media";
        $plain = $this->media($caregiver);
        $foreign = $this->media($fixture['other_caregiver']);
        $pendingDelete = $this->media($caregiver, 'PENDING_DELETE');
        $petPhoto = $this->media($caregiver);
        DB::table('pets')->where('id', $fixture['pet']->id)->update(['photo_media_id' => $petPhoto->id]);
        $careUpdateMedia = $this->media($caregiver);
        $booking = BookingFixture::requested($this)['booking'];
        DB::table('care_updates')->insert([
            'booking_id' => $booking->id,
            'sender_id' => $caregiver->id,
            'body' => null,
            'media_object_id' => $careUpdateMedia->id,
            'visibility' => 'VISIBLE',
            'created_at' => now(),
        ]);

        $invalidMedia = [
            [['media_object_id' => (string) $plain->id, 'sort_order' => 1], ['media_object_id' => (string) $plain->id, 'sort_order' => 2]],
            [['media_object_id' => (string) $foreign->id, 'sort_order' => 1]],
            [['media_object_id' => (string) $pendingDelete->id, 'sort_order' => 1]],
            [['media_object_id' => (string) $petPhoto->id, 'sort_order' => 1]],
            [['media_object_id' => (string) $careUpdateMedia->id, 'sort_order' => 1]],
            [['media_object_id' => (string) $plain->id, 'sort_order' => 2]],
        ];
        foreach ($invalidMedia as $items) {
            $this->actingAs($caregiver)->putJson($url, ['media' => $items])->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
        $tooMany = array_map(fn (int $order): array => [
            'media_object_id' => (string) $order,
            'sort_order' => $order,
        ], range(1, 11));
        $this->actingAs($caregiver)->putJson($url, ['media' => $tooMany])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('listing_media', 0);
    }

    public function test_only_draft_listing_media_pivot_can_be_changed_and_audit_failure_rolls_back_reorder(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['draft_listing_id'];
        $first = $this->media($caregiver);
        $second = $this->media($caregiver);
        DB::table('listing_media')->insert([
            ['listing_id' => $listingId, 'media_object_id' => $first->id, 'sort_order' => 1],
        ]);
        $url = "/api/caregiver/listings/{$listingId}/media";

        foreach (['PENDING_REVIEW', 'ACTIVE', 'SUSPENDED', 'ARCHIVED'] as $status) {
            DB::table('listings')->where('id', $listingId)->update([
                'status' => $status,
                'suspension_source' => $status === 'SUSPENDED' ? 'CAREGIVER' : null,
            ]);
            $this->actingAs($caregiver)->putJson($url, ['media' => [
                ['media_object_id' => (string) $second->id, 'sort_order' => 1],
            ]])->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
            $this->assertDatabaseHas('listing_media', ['listing_id' => $listingId, 'media_object_id' => $first->id, 'sort_order' => 1]);
        }

        DB::table('listings')->where('id', $listingId)->update(['status' => 'DRAFT', 'suspension_source' => null]);
        $workflow = Mockery::mock(ListingWorkflow::class)->makePartial();
        $workflow->shouldReceive('audit')->once()->andThrow(new RuntimeException('audit write failed'));
        $this->app->instance(ListingWorkflow::class, $workflow);

        $this->putJson($url, ['media' => [
            ['media_object_id' => (string) $second->id, 'sort_order' => 1],
        ]])->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->assertDatabaseHas('listing_media', ['listing_id' => $listingId, 'media_object_id' => $first->id, 'sort_order' => 1]);
        $this->assertDatabaseMissing('listing_media', ['listing_id' => $listingId, 'media_object_id' => $second->id]);
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'version' => 1]);
    }

    public function test_private_preview_is_scoped_authenticated_and_change_request_media_stays_hidden_from_public(): void
    {
        Storage::fake('local');
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $listingId = $fixture['draft_listing_id'];
        $current = $this->uploadedMedia($caregiver);
        $proposal = $this->uploadedMedia($caregiver);

        $this->putJson("/api/caregiver/listings/{$listingId}/media", ['media' => [
            ['media_object_id' => (string) $current->id, 'sort_order' => 1],
        ]])->assertOk();
        $this->getJson("/api/caregiver/listings/{$fixture['active_listing_id']}/media/{$current->id}/content")
            ->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/caregiver/listings/{$listingId}/media/{$current->id}/content")->assertUnauthorized();

        $this->actingAs($caregiver)->getJson("/api/caregiver/listings/{$listingId}/media/{$current->id}/content")
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($fixture['other_caregiver'])
            ->getJson("/api/caregiver/listings/{$listingId}/media/{$current->id}/content")->assertNotFound();
        $this->actingAs($admin)->getJson("/api/admin/listings/{$listingId}/media/{$current->id}/content")
            ->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/submit")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$listingId}/approve")->assertOk();
        $this->getJson("/api/listings/{$listingId}/media/{$proposal->id}/content")->assertNotFound();
        $request = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => DB::table('listings')->where('id', $listingId)->value('version'),
            'changes' => ['media' => [['media_object_id' => (string) $proposal->id, 'sort_order' => 1]]],
        ])->assertCreated();
        $changeId = $request->json('data.id');

        $this->actingAs($admin)->getJson("/api/admin/listings/{$listingId}/media/{$proposal->id}/content")
            ->assertNotFound();
        $this->getJson("/api/admin/listings/{$listingId}/media/{$proposal->id}/content?change_request_id={$changeId}")
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson("/api/listings/{$listingId}/media/{$proposal->id}/content")->assertNotFound();

        DB::table('media_objects')->where('id', $current->id)->update(['state' => 'PENDING_DELETE']);
        $this->actingAs($caregiver)->getJson("/api/caregiver/listings/{$listingId}")
            ->assertOk()->assertJsonPath('data.media', []);
        $this->getJson("/api/caregiver/listings/{$listingId}/media/{$current->id}/content")->assertNotFound();
    }

    private function postImage(User $owner, UploadedFile $file, array $extra = []): TestResponse
    {
        try {
            return $this->actingAs($owner)->postJson('/api/caregiver/media', ['file' => $file, ...$extra]);
        } finally {
            if (is_file($file->getPathname())) {
                @unlink($file->getPathname());
            }
        }
    }

    private function uploadedMedia(User $owner): MediaObject
    {
        $response = $this->postImage($owner, $this->image('png'))->assertCreated();

        return MediaObject::query()->findOrFail($response->json('data.id'));
    }

    private function media(User $owner, string $state = 'READY'): MediaObject
    {
        $media = new MediaObject([
            'object_key' => 'listings/'.$owner->id.'/fixture-'.fake()->uuid().'.png',
            'mime_type' => 'image/png',
            'byte_size' => 100,
            'sha256' => hash('sha256', fake()->uuid()),
        ]);
        $media->owner()->associate($owner);
        $media->state = $state;
        $media->save();

        return $media;
    }

    private function image(string $format, int $width = 80, int $height = 60): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pettel-upload-');
        $image = imagecreatetruecolor($width, $height);
        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $saved = match ($format) {
            'jpeg' => imagejpeg($image, $path, 85),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 85),
        };
        imagedestroy($image);
        if (! $saved) {
            @unlink($path);
            $this->fail("GD could not create a {$format} fixture.");
        }

        return new UploadedFile($path, "upload.{$extension}", null, UPLOAD_ERR_OK, true);
    }

    private function oversizedPng(int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pettel-pixel-');
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $header = pack('NNC5', $width, $height, 8, 6, 0, 0, 0);
        $bytes = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', $header)
            .$chunk('IDAT', gzcompress("\0\0\0\0\0"))
            .$chunk('IEND', '');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'oversized.png', null, UPLOAD_ERR_OK, true);
    }

    private function thumbnailKey(string $key): string
    {
        return pathinfo($key, PATHINFO_DIRNAME).'/'.pathinfo($key, PATHINFO_FILENAME).'-thumb.webp';
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
