<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class PublicListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $total = $this->getAttributes()['price_total_vnd'] ?? null;

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'area_code' => $this->area_code,
            'price_vnd' => $this->price_vnd,
            'price_unit' => $this->price_unit,
            'acceptance_rules_json' => $this->acceptance_rules_json,
            'media' => $this->whenLoaded('media', fn () => $this->media->filter(
                fn ($media): bool => $media->state === 'READY' && $media->visibility === 'PUBLIC_ELIGIBLE'
            )->map(fn ($media): array => [
                'media_object_id' => (string) $media->id,
                'sort_order' => (int) $media->pivot->sort_order,
                'image_url' => route('listings.media.content', ['id' => $this->id, 'mediaId' => $media->id]),
                'thumbnail_url' => route('listings.media.content', ['id' => $this->id, 'mediaId' => $media->id, 'variant' => 'thumbnail']),
            ])->values()),
            'price_total_vnd' => $total === null ? null : (int) $total,
        ];
    }
}
