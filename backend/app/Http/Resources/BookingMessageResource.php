<?php

namespace App\Http\Resources;

use App\Models\MediaObject;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class BookingMessageResource extends JsonResource
{
    /**
     * @return array{
     *   id: string, booking_id: string, sender_id: string, body: ?string, created_at: string,
     *   attachments: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'booking_id' => (string) $this->booking_id,
            'sender_id' => (string) $this->sender_id,
            'body' => $this->body,
            'created_at' => self::formatTimestamp($this->created_at),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(
                fn (MediaObject $media): array => [
                    'id' => (string) $media->getKey(),
                    'mime_type' => $media->mime_type,
                    'byte_size' => $media->byte_size,
                    'url' => route('bookings.messages.attachment', [
                        'id' => $this->booking_id,
                        'messageId' => $this->getKey(),
                        'mediaId' => $media->getKey(),
                    ], absolute: false),
                ],
            )),
        ];
    }

    private static function formatTimestamp(mixed $value): string
    {
        $timestamp = $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, 'UTC');

        return $timestamp->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
