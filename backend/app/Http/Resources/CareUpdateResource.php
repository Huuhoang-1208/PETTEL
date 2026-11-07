<?php

namespace App\Http\Resources;

use App\Models\CareUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin CareUpdate */
class CareUpdateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $mediaUrl = null;
        if ($this->media !== null && $this->redacted_at === null) {
            $mediaUrl = URL::temporarySignedRoute(
                'bookings.care-updates.image',
                now('UTC')->addMinutes(5),
                ['id' => $this->booking_id, 'careUpdateId' => $this->id],
            );
        }

        return [
            'id' => (string) $this->id,
            'booking_id' => (string) $this->booking_id,
            'body' => $this->body,
            'sender' => [
                'id' => (string) $this->sender_id,
                'name' => $this->sender?->name,
            ],
            'created_at' => CarbonImmutable::parse($this->created_at)->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            'image_url' => $mediaUrl,
            'redacted' => $this->redacted_at !== null,
        ];
    }
}
