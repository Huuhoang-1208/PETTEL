<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'area_code' => $this->area_code,
            'price_vnd' => $this->price_vnd,
            'price_unit' => $this->price_unit,
            'acceptance_rules_json' => $this->acceptance_rules_json,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'suspension_source' => $this->suspension_source,
            'version' => $this->version,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'latest_decision' => $this->latest_decision_action === null ? null : [
                'action' => (string) $this->latest_decision_action,
                'reason' => $this->latest_decision_reason,
                'decided_at' => CarbonImmutable::parse($this->latest_decision_at, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ],
            'pending_change_request_id' => $this->pending_change_request_id === null
                ? null : (string) $this->pending_change_request_id,
            'media' => $this->whenLoaded('media', fn () => $this->media->filter(
                fn ($media): bool => $media->state === 'READY'
            )->map(fn ($media): array => [
                'media_object_id' => (string) $media->id,
                'sort_order' => (int) $media->pivot->sort_order,
                'image_url' => $request->is('api/admin/*')
                    ? url("/api/admin/listings/{$this->id}/media/{$media->id}/content")
                    : url("/api/caregiver/listings/{$this->id}/media/{$media->id}/content"),
                'thumbnail_url' => ($request->is('api/admin/*')
                    ? url("/api/admin/listings/{$this->id}/media/{$media->id}/content")
                    : url("/api/caregiver/listings/{$this->id}/media/{$media->id}/content")).'?variant=thumbnail',
            ])->values()),
        ];
    }
}
