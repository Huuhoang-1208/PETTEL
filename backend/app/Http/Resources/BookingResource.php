<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'status' => $this->status,
            'pet_id' => (string) $this->pet_id,
            'listing_id' => (string) $this->listing_id,
            'start_at' => $this->start_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $this->end_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'price_snapshot_vnd' => $this->price_snapshot_vnd,
            'payment_due_at' => $this->payment_due_at?->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
