<?php

namespace App\Http\Controllers;

use App\Domain\Listing\Actions\ListingError;
use App\Domain\Listing\Actions\ListingInput;
use App\Domain\Listing\Actions\ListingWorkflow;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ListingMediaController extends Controller
{
    public function upload(Request $request): mixed
    {
        $validated = app(ListingInput::class)->body($request, ['file'], [
            'file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);
        $file = $validated['file'];
        $bytes = file_get_contents($file->getRealPath());
        if (! is_string($bytes)) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Unable to read image.', ['file' => ['Choose a readable image.']]);
        }
        $dimensions = @getimagesizefromstring($bytes);
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 20_000_000) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Image dimensions exceed the allowed limit.', ['file' => ['Maximum 20 megapixels.']]);
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Invalid image file.', ['file' => ['Upload a valid JPEG, PNG or WebP image.']]);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width * $height > 20_000_000) {
            imagedestroy($image);
            ListingError::fail(422, 'VALIDATION_ERROR', 'Image dimensions exceed the allowed limit.', ['file' => ['Maximum 20 megapixels.']]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $format = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => null,
        };
        if ($format === null) {
            imagedestroy($image);
            ListingError::fail(422, 'VALIDATION_ERROR', 'Unsupported image content.', ['file' => ['Upload a valid JPEG, PNG or WebP image.']]);
        }
        $normalized = $this->encode($image, $format);
        $thumb = $this->thumbnail($image);
        imagedestroy($image);
        $key = 'listings/'.$request->user()->id.'/'.Str::uuid().'.'.$format;
        $thumbKey = $this->thumbnailKey($key);
        $disk = Storage::disk('local');
        try {
            if (! $disk->put($key, $normalized) || ! $disk->put($thumbKey, $thumb)) {
                throw new \RuntimeException('Image storage failed.');
            }
            $media = DB::transaction(function () use ($request, $key, $normalized, $mime): MediaObject {
                $media = new MediaObject([
                    'object_key' => $key,
                    'mime_type' => $mime,
                    'byte_size' => strlen($normalized),
                    'sha256' => hash('sha256', $normalized),
                ]);
                $media->owner()->associate($request->user());
                $media->save();

                return $media;
            });
        } catch (\Throwable $exception) {
            $failedKeys = [];
            $cleanupErrors = [];
            foreach ([$key, $thumbKey] as $path) {
                try {
                    if (! $disk->delete($path)) {
                        $failedKeys[] = $path;
                    }
                } catch (\Throwable $cleanupException) {
                    $failedKeys[] = $path;
                    $cleanupErrors[] = $cleanupException::class;
                }
            }
            if ($failedKeys !== []) {
                Log::error('listing.media.upload_cleanup_failed', [
                    'owner_user_id' => $request->user()->id,
                    'object_keys' => $failedKeys,
                    'upload_exception' => $exception::class,
                    'cleanup_exceptions' => $cleanupErrors,
                ]);
            }
            report($exception);
            ListingError::fail(500, 'MEDIA_UPLOAD_FAILED', 'Image upload failed.');
        }

        return response()->json(['data' => [
            'id' => (string) $media->id, 'mime_type' => $mime, 'byte_size' => $media->byte_size,
        ]], 201);
    }

    public function ownerContent(Request $request, string $mediaId): Response
    {
        $media = MediaObject::query()->whereKey($mediaId)->where('owner_user_id', $request->user()->id)
            ->where('state', 'READY')->whereNotIn('id', MediaObject::messageAttachmentIds())->first();
        if ($media === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }

        $query = $this->contentQuery($request);

        return $this->serve($media, $query['variant'] ?? 'image');
    }

    public function caregiverContent(Request $request, string $id, string $mediaId): Response
    {
        $listing = Listing::query()->whereKey($id)->whereRelation('caregiverProfile', 'user_id', $request->user()->id)->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }
        $media = MediaObject::query()->whereKey($mediaId)->where('owner_user_id', $request->user()->id)
            ->where('state', 'READY')->whereNotIn('id', MediaObject::messageAttachmentIds())->first();
        if ($media === null || ! $this->listingReferencesMedia($listing, $mediaId)) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }
        $query = $this->contentQuery($request);

        return $this->serve($media, $query['variant'] ?? 'image');
    }

    public function adminContent(Request $request, string $id, string $mediaId): Response
    {
        $listing = Listing::query()->whereKey($id)->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }
        if ((string) $listing->caregiverProfile()->value('user_id') === (string) $request->user()->id) {
            ListingError::fail(403, 'FORBIDDEN', 'An administrator cannot review their own listing.');
        }
        $media = MediaObject::query()->whereKey($mediaId)->where('state', 'READY')
            ->whereNotIn('id', MediaObject::messageAttachmentIds())->first();
        if ($media === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }
        $changeRequestId = $request->query('change_request_id');
        if ($changeRequestId !== null && (! is_string($changeRequestId) || ! preg_match('/^[1-9][0-9]*$/', $changeRequestId))) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Invalid media query.', ['change_request_id' => ['Use a positive integer ID.']]);
        }
        if (! $this->listingReferencesMedia($listing, $mediaId, $changeRequestId)) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }
        $query = $this->contentQuery($request, true);

        return $this->serve($media, $query['variant'] ?? 'image');
    }

    public function publicContent(Request $request, string $id, string $mediaId): Response
    {
        $listing = Listing::query()->whereKey($id)->where('status', 'ACTIVE')
            ->whereRelation('caregiverProfile', 'deleted_at', null)
            ->whereRelation('caregiverProfile.user', 'status', 'ACTIVE')
            ->whereRelation('caregiverProfile.user.roles', 'role', 'CAREGIVER')->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }
        $media = $listing->media()->whereKey($mediaId)->where('media_objects.state', 'READY')
            ->where('media_objects.visibility', 'PUBLIC_ELIGIBLE')->first();
        if ($media === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }
        $query = $this->contentQuery($request);

        return $this->serve($media, $query['variant'] ?? 'image');
    }

    public function updateDraft(Request $request, string $id, ListingWorkflow $workflow): mixed
    {
        $listing = Listing::query()->whereKey($id)->whereRelation('caregiverProfile', 'user_id', $request->user()->id)->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }
        $validated = app(ListingInput::class)->body($request, ['media'], ['media' => ['present', 'array', 'max:10', 'list'],
            'media.*' => ['array:media_object_id,sort_order'],
            'media.*.media_object_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/'],
            'media.*.sort_order' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        DB::transaction(function () use ($request, $listing, $validated, $workflow): void {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'DRAFT') {
                ListingError::fail(409, 'INVALID_STATE', 'Only DRAFT images can be changed directly.');
            }
            $this->assertOwnReadyMedia($request->user(), $validated['media']);
            $this->assertOrderedMedia($validated['media']);
            $before = DB::table('listing_media')->where('listing_id', $locked->id)
                ->orderBy('sort_order')->get(['media_object_id', 'sort_order'])
                ->map(fn ($m) => [(string) $m->media_object_id, (int) $m->sort_order])->all();
            $after = array_map(fn ($m) => [(string) $m['media_object_id'], (int) $m['sort_order']], $validated['media']);
            if ($before !== $after) {
                $listingBefore = $workflow->snapshot($locked);
                $workflow->setMedia($locked, $validated['media']);
                $locked->version++;
                $locked->save();
                $workflow->audit($request, $request->user(), $locked, 'LISTING_MEDIA_UPDATED', null, $listingBefore);
            }
        }, 3);

        return response()->json(['data' => ListingResource::make(Listing::query()->with('media')->findOrFail($listing->id))]);
    }

    /** @return array<string, mixed> */
    private function contentQuery(Request $request, bool $admin = false): array
    {
        $allowed = $admin ? ['variant', 'change_request_id'] : ['variant'];

        return app(ListingInput::class)->query($request, $allowed, [
            'variant' => ['sometimes', 'in:image,thumbnail'],
            'change_request_id' => ['sometimes', 'regex:/^[1-9][0-9]*$/'],
        ]);
    }

    private function listingReferencesMedia(Listing $listing, string $mediaId, ?string $changeRequestId = null): bool
    {
        if ($listing->media()->whereKey($mediaId)->exists()) {
            return true;
        }
        if ($changeRequestId === null) {
            return false;
        }
        $change = DB::table('listing_change_requests')->where('id', $changeRequestId)
            ->where('listing_id', $listing->id)->where('status', 'PENDING_REVIEW')->first();
        if ($change === null) {
            return false;
        }
        $data = json_decode($change->proposed_data_json, true);
        $items = is_array($data) && isset($data['data']['media']) && is_array($data['data']['media'])
            ? $data['data']['media'] : [];
        foreach ($items as $item) {
            if (is_array($item) && (string) ($item['media_object_id'] ?? '') === $mediaId) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{media_object_id: string, sort_order: int}> $items */
    private function assertOwnReadyMedia(User $owner, array $items): void
    {
        $ids = array_column($items, 'media_object_id');
        if ($ids === []) {
            return;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Media IDs must be unique.');
        }
        $count = MediaObject::query()->whereIn('id', $ids)->where('owner_user_id', $owner->id)
            ->where('state', 'READY')->whereNotIn('id', MediaObject::messageAttachmentIds())->count();
        if ($count !== count($ids) || DB::table('pets')->whereIn('photo_media_id', $ids)->exists()
            || DB::table('care_updates')->whereIn('media_object_id', $ids)->exists()) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'One or more images cannot be used.');
        }
    }

    /** @param list<array{media_object_id: string, sort_order: int}> $items */
    private function assertOrderedMedia(array $items): void
    {
        if (array_column($items, 'sort_order') !== ($items === [] ? [] : range(1, count($items)))) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Image order must be continuous from 1.');
        }
    }

    private function serve(MediaObject $media, string $variant): Response
    {
        if (! in_array($variant, ['image', 'thumbnail'], true)) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Invalid image variant.');
        }
        $key = $variant === 'image' ? $media->object_key : $this->thumbnailKey($media->object_key);
        $disk = Storage::disk('local');
        if (! $disk->exists($key)) {
            ListingError::fail(404, 'NOT_FOUND', 'Media not found.');
        }

        return response($disk->get($key), 200, [
            'Content-Type' => $variant === 'thumbnail' ? 'image/webp' : $media->mime_type,
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param 'jpg'|'png'|'webp' $format */
    private function encode(\GdImage $image, string $format): string
    {
        ob_start();
        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, 88),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, 88),
        };
        $bytes = ob_get_clean();
        if (! $ok || ! is_string($bytes)) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Image could not be processed.');
        }

        return $bytes;
    }

    private function thumbnail(\GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 640 / max($width, $height));
        $thumb = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);
        ob_start();
        $ok = imagewebp($thumb, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($thumb);
        if (! $ok || ! is_string($bytes)) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Thumbnail could not be processed.');
        }

        return $bytes;
    }

    private function thumbnailKey(string $key): string
    {
        return pathinfo($key, PATHINFO_DIRNAME).'/'.pathinfo($key, PATHINFO_FILENAME).'-thumb.webp';
    }
}
