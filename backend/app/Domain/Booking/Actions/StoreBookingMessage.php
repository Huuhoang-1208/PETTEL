<?php

namespace App\Domain\Booking\Actions;

use App\Models\MediaObject;
use App\Models\Message;
use App\Models\User;
use finfo;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreBookingMessage
{
    public function __construct(private BookingMessageAccess $access) {}

    /** @param list<UploadedFile> $attachments */
    public function handle(User $actor, string $bookingId, ?string $body, array $attachments): Message
    {
        $images = $this->inspect($attachments);
        $disk = Storage::disk('local');
        $attemptedPaths = [];
        $storedImages = [];

        try {
            foreach ($images as $image) {
                $name = (string) Str::uuid();
                $path = 'messages/'.$name;
                $attemptedPaths[] = $path;

                if ($disk->putFileAs('messages', $image['file'], $name, ['visibility' => 'private']) === false) {
                    throw new RuntimeException('Unable to store booking message image.');
                }

                $storedImages[] = [
                    'object_key' => $path,
                    'mime_type' => $image['mime_type'],
                    'byte_size' => $image['byte_size'],
                    'sha256' => $image['sha256'],
                ];
            }

            return DB::transaction(function () use ($actor, $bookingId, $body, $storedImages): Message {
                $booking = $this->access->bookingForSend($actor, $bookingId);
                $message = $booking->messages()->create(['sender_id' => $actor->getKey(), 'body' => $body]);
                if (! $message->exists) {
                    throw new RuntimeException('Unable to create booking message.');
                }

                foreach ($storedImages as $image) {
                    $media = new MediaObject($image);
                    $media->owner()->associate($actor);
                    if (! $media->save()) {
                        throw new RuntimeException('Unable to create booking message media.');
                    }
                    $message->attachments()->attach($media);
                }

                return $message;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($attemptedPaths as $path) {
                try {
                    if ($disk->delete($path)) {
                        continue;
                    }
                } catch (Throwable) {
                    // Report the failed cleanup without logging file or message contents.
                }

                Log::error('booking.message.upload_cleanup_failed', ['object_key' => $path]);
            }

            if ($exception instanceof HttpResponseException) {
                throw $exception;
            }

            Log::error('booking.message.save_failed', ['booking_id' => $bookingId, 'exception' => $exception::class]);

            throw new HttpResponseException(response()->json(['error' => [
                'code' => $attachments === [] ? 'MESSAGE_SAVE_FAILED' : 'MEDIA_UPLOAD_FAILED',
                'message' => 'Unable to save the booking message.',
                'details' => [],
                'request_id' => (string) Str::uuid(),
            ]], 500));
        }
    }

    /**
     * @param  list<UploadedFile>  $attachments
     * @return list<array{file: UploadedFile, mime_type: string, byte_size: positive-int, sha256: string}>
     */
    private function inspect(array $attachments): array
    {
        $totalBytes = 0;
        $images = [];
        $detector = new finfo(FILEINFO_MIME_TYPE);

        foreach ($attachments as $index => $file) {
            $path = $file->getPathname();
            $size = filesize($path);
            if ($size === false || $size < 1) {
                throw ValidationException::withMessages(["attachments.{$index}" => ['Upload a readable image.']]);
            }

            $totalBytes += $size;
            if ($totalBytes > 10_000_000) {
                throw ValidationException::withMessages(['attachments' => ['The combined size must not exceed 10 MB.']]);
            }

            $mime = $detector->file($path);
            $dimensions = @getimagesize($path);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || $dimensions === false || $dimensions['mime'] !== $mime) {
                throw ValidationException::withMessages(["attachments.{$index}" => ['Upload a valid JPEG, PNG or WebP image.']]);
            }

            $hash = hash_file('sha256', $path);
            if ($hash === false) {
                throw ValidationException::withMessages(["attachments.{$index}" => ['Upload a readable image.']]);
            }

            $images[] = ['file' => $file, 'mime_type' => $mime, 'byte_size' => $size, 'sha256' => $hash];
        }

        return $images;
    }
}
