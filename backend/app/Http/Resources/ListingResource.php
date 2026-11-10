<?php

namespace App\Http\Resources;

use App\Models\Listing;
use App\Models\MediaObject;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    /**
     * @return array{
     *   id: string, title: string, description: string, service_type: string, area_code: string,
     *   price_vnd: int|string, price_unit: string,
     *   acceptance_rules_json: array{schema_version: int, species: list<string>, sizes: list<string>}, capacity: int,
     *   status: string, suspension_source: ?string, version: int, published_at: ?string,
     *   created_at: ?string, updated_at: ?string,
     *   latest_decision: ?array{action: string, reason: ?string, decided_at: string},
     *   pending_change_request_id: ?string, media: mixed
     * }
     */
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
                'decided_at' => self::formatTimestamp((string) $this->latest_decision_at),
            ],
            'pending_change_request_id' => $this->pending_change_request_id === null
                ? null : (string) $this->pending_change_request_id,
            'media' => $this->whenLoaded('media', fn () => $this->media->filter(
                fn (MediaObject $media): bool => $media->state === 'READY'
            )->map(fn (MediaObject $media): array => [
                'media_object_id' => (string) $media->id,
                'sort_order' => (int) $media->pivot->sort_order,
                'image_url' => (string) ($request->is('api/admin/*')
                    ? url("/api/admin/listings/{$this->id}/media/{$media->id}/content")
                    : url("/api/caregiver/listings/{$this->id}/media/{$media->id}/content")),
                'thumbnail_url' => (string) (($request->is('api/admin/*')
                    ? url("/api/admin/listings/{$this->id}/media/{$media->id}/content")
                    : url("/api/caregiver/listings/{$this->id}/media/{$media->id}/content")).'?variant=thumbnail'),
            ])->values()),
        ];
    }

    private static function formatTimestamp(string $value): string
    {
        return CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
