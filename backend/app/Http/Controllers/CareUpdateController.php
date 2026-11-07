<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\CareUpdateError;
use App\Http\Resources\CareUpdateResource;
use App\Models\Booking;
use App\Models\CareUpdate;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CareUpdateController extends Controller
{
    public function index(Request $request, string $id): mixed
    {
        $booking = Booking::query()->whereKey($id)->first();
        if ($booking === null) {
            CareUpdateError::fail(404, 'NOT_FOUND', 'Booking not found.');
        }
        $this->assertParticipant($request->user(), $booking);

        if (array_diff(array_keys($request->query()), ['page', 'per_page']) !== []) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Unknown query parameters.');
        }
        $pagination = validator($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($pagination->fails()) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Invalid pagination parameters.', $pagination->errors()->toArray());
        }
        $perPage = (int) ($pagination->validated()['per_page'] ?? 50);

        $updates = CareUpdate::query()->with(['sender', 'media'])
            ->where('booking_id', $booking->id)
            ->orderBy('created_at')->orderBy('id')
            ->paginate((int) $perPage);

        return CareUpdateResource::collection($updates);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $booking = Booking::query()->whereKey($id)->first();
        if ($booking === null) {
            CareUpdateError::fail(404, 'NOT_FOUND', 'Booking not found.');
        }
        $user = $request->user();
        $this->assertCaregiverForBooking($user, $booking);
        if ($booking->status !== 'IN_PROGRESS') {
            CareUpdateError::fail(409, 'INVALID_STATE', 'Care updates can only be created during IN_PROGRESS.');
        }

        $unknown = array_diff(array_keys($request->all()), ['body', 'image']);
        if ($unknown !== []) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Unknown request fields.', array_fill_keys($unknown, ['Unknown field.']));
        }
        $validator = validator($request->all(), [
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);
        if ($validator->fails()) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Invalid care update.', $validator->errors()->toArray());
        }
        /** @var array{body?: string|null, image?: UploadedFile|null} $data */
        $data = $validator->validated();
        $body = isset($data['body']) ? trim($data['body']) : null;
        $body = $body === '' ? null : $body;
        $file = $data['image'] ?? null;
        if ($body === null && $file === null) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Provide text, an image, or both.', [
                'body' => ['Text or image is required.'],
                'image' => ['Text or image is required.'],
            ]);
        }

        $image = $file === null ? null : $this->normalizeImage($file);
        $key = $image === null ? null : 'care-updates/'.$booking->id.'/'.Str::uuid().'.'.$image['extension'];
        $disk = Storage::disk('local');
        if ($image !== null && $key !== null) {
            try {
                if (! $disk->put($key, $image['bytes'])) {
                    throw new \RuntimeException('Image storage failed.');
                }
            } catch (\Throwable $exception) {
                $this->cleanupUpload($key, $user, $exception);
                report($exception);
                CareUpdateError::fail(500, 'MEDIA_UPLOAD_FAILED', 'Image upload failed.');
            }
        }

        try {
            $careUpdate = DB::transaction(function () use ($id, $user, $body, $image, $key): CareUpdate {
                $lockedBooking = Booking::query()->whereKey($id)->lockForUpdate()->first();
                if ($lockedBooking === null) {
                    CareUpdateError::fail(404, 'NOT_FOUND', 'Booking not found.');
                }
                $this->assertCaregiverForBooking($user, $lockedBooking);
                if ($lockedBooking->status !== 'IN_PROGRESS') {
                    CareUpdateError::fail(409, 'INVALID_STATE', 'Care updates can only be created during IN_PROGRESS.');
                }

                $media = null;
                if ($image !== null && $key !== null) {
                    $media = new MediaObject([
                        'object_key' => $key,
                        'mime_type' => $image['mime'],
                        'byte_size' => strlen($image['bytes']),
                        'sha256' => hash('sha256', $image['bytes']),
                    ]);
                    $media->owner()->associate($user);
                    $media->save();
                }

                $careUpdate = new CareUpdate([
                    'body' => $body,
                    'visibility' => 'VISIBLE',
                    'created_at' => now('UTC'),
                ]);
                $careUpdate->booking()->associate($lockedBooking);
                $careUpdate->sender()->associate($user);
                if ($media !== null) {
                    $careUpdate->media()->associate($media);
                }
                $careUpdate->save();

                return $careUpdate->load(['sender', 'media']);
            }, 3);
        } catch (\Throwable $exception) {
            if ($key !== null) {
                $this->cleanupUpload($key, $user, $exception);
            }
            throw $exception;
        }

        return response()->json(['data' => CareUpdateResource::make($careUpdate)->resolve($request)], 201);
    }

    public function image(Request $request, string $id, string $careUpdateId): Response
    {
        $booking = Booking::query()->whereKey($id)->first();
        if ($booking === null) {
            CareUpdateError::fail(404, 'NOT_FOUND', 'Image not found.');
        }
        $this->assertParticipant($request->user(), $booking);
        $update = CareUpdate::query()->whereKey($careUpdateId)->where('booking_id', $booking->id)
            ->whereNull('redacted_at')->with('media')->first();
        $media = $update?->media;
        if ($update === null || $media === null || $media->state !== 'READY') {
            CareUpdateError::fail(404, 'NOT_FOUND', 'Image not found.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($media->object_key)) {
            CareUpdateError::fail(404, 'NOT_FOUND', 'Image not found.');
        }

        return response($disk->get($media->object_key), 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertParticipant(?User $user, Booking $booking): void
    {
        if ($user === null) {
            CareUpdateError::fail(401, 'UNAUTHENTICATED', 'Login required.');
        }
        if ((string) $booking->owner_id !== (string) $user->id
            && (string) $booking->caregiver_user_id !== (string) $user->id) {
            CareUpdateError::fail(403, 'FORBIDDEN', 'You cannot access this booking.');
        }
    }

    private function assertCaregiverForBooking(?User $user, Booking $booking): void
    {
        $this->assertParticipant($user, $booking);
        if ($user === null || (string) $booking->caregiver_user_id !== (string) $user->id || ! $user->hasRole('CAREGIVER')) {
            CareUpdateError::fail(403, 'FORBIDDEN', 'Only this booking caregiver can create care updates.');
        }
    }

    /** @return array{bytes: string, mime: string, extension: string} */
    private function normalizeImage(UploadedFile $file): array
    {
        $bytes = file_get_contents($file->getRealPath());
        if (! is_string($bytes)) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Unable to read image.', ['image' => ['Choose a readable image.']]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => null,
        };
        $dimensions = @getimagesizefromstring($bytes);
        if ($extension === null || $dimensions === false || $dimensions[0] * $dimensions[1] > 20_000_000) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Upload a valid JPEG, PNG or WebP image.', [
                'image' => ['Upload a valid JPEG, PNG or WebP image.'],
            ]);
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Upload a valid JPEG, PNG or WebP image.', [
                'image' => ['Upload a valid JPEG, PNG or WebP image.'],
            ]);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width * $height > 20_000_000) {
            imagedestroy($image);
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Image dimensions exceed the allowed limit.', [
                'image' => ['Maximum 20 megapixels.'],
            ]);
        }

        ob_start();
        $encoded = match ($extension) {
            'jpg' => imagejpeg($image, null, 88),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, 88),
        };
        $normalized = ob_get_clean();
        imagedestroy($image);
        if (! $encoded || ! is_string($normalized) || $normalized === '') {
            CareUpdateError::fail(422, 'VALIDATION_ERROR', 'Image could not be processed.');
        }

        return ['bytes' => $normalized, 'mime' => (string) $mime, 'extension' => $extension];
    }

    private function cleanupUpload(string $key, User $user, \Throwable $exception): void
    {
        try {
            if (Storage::disk('local')->exists($key) && ! Storage::disk('local')->delete($key)) {
                throw new \RuntimeException('Storage delete returned false.');
            }
        } catch (\Throwable $cleanupException) {
            Log::error('care_update.media.upload_cleanup_failed', [
                'owner_user_id' => $user->id,
                'object_key' => $key,
                'upload_exception' => $exception::class,
                'cleanup_exception' => $cleanupException::class,
            ]);
        }
    }
}
