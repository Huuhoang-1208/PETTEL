<?php

namespace App\Domain\Listing\Actions;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingWorkflow
{
    /** @return array<string, mixed> */
    public function snapshot(Listing $listing): array
    {
        return [
            'status' => $listing->status,
            'version' => (int) $listing->version,
            'suspension_source' => $listing->suspension_source,
            'title' => $listing->title,
            'description' => $listing->description,
            'service_type' => $listing->service_type,
            'area_code' => $listing->area_code,
            'price_vnd' => $listing->price_vnd,
            'price_unit' => $listing->price_unit,
            'acceptance_rules_json' => $listing->acceptance_rules_json,
            'media' => DB::table('listing_media')->where('listing_id', $listing->id)
                ->orderBy('sort_order')->get(['media_object_id', 'sort_order'])
                ->map(fn ($media) => ['media_object_id' => (string) $media->media_object_id, 'sort_order' => (int) $media->sort_order])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $metadata
     */
    public function audit(
        Request $request,
        User $actor,
        Listing $listing,
        string $action,
        ?string $reason,
        array $before,
        array $metadata = [],
        string $resourceType = 'LISTING',
        int|string|null $resourceId = null,
    ): void {
        $requestId = $request->attributes->get('listing_audit_request_id');
        if (! is_string($requestId)) {
            $headerId = $request->header('X-Request-ID');
            $requestId = is_string($headerId) && Str::isUuid($headerId) ? $headerId : (string) Str::uuid();
            $request->attributes->set('listing_audit_request_id', $requestId);
        }

        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId ?? $listing->id,
            'reason' => $reason,
            'request_id' => $requestId,
            'metadata_json' => json_encode([
                'from' => $before,
                'to' => $this->snapshot($listing),
                ...$metadata,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $listingBefore */
    public function withdrawPending(Listing $listing, User $actor, string $reason, Request $request, array $listingBefore): void
    {
        $pending = DB::table('listing_change_requests')
            ->where('listing_id', $listing->id)
            ->where('status', 'PENDING_REVIEW')
            ->lockForUpdate()
            ->get();

        foreach ($pending as $change) {
            DB::table('listing_change_requests')->where('id', $change->id)->update([
                'status' => 'WITHDRAWN',
                'decision_reason' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'updated_at' => now(),
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'action' => 'LISTING_CHANGE_WITHDRAWN',
                'resource_type' => 'LISTING_CHANGE_REQUEST',
                'resource_id' => $change->id,
                'reason' => $reason,
                'request_id' => $this->requestId($request),
                'metadata_json' => json_encode([
                    'listing_id' => $listing->id,
                    'from' => [
                        'listing_status' => $listingBefore['status'],
                        'listing_version' => $listingBefore['version'],
                        'change_request_status' => 'PENDING_REVIEW',
                    ],
                    'to' => [
                        'listing_status' => $listing->status,
                        'listing_version' => (int) $listing->version,
                        'change_request_status' => 'WITHDRAWN',
                    ],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('listing_audit_request_id');
        if (is_string($requestId)) {
            return $requestId;
        }

        $headerId = $request->header('X-Request-ID');
        $requestId = is_string($headerId) && Str::isUuid($headerId) ? $headerId : (string) Str::uuid();
        $request->attributes->set('listing_audit_request_id', $requestId);

        return $requestId;
    }

    /** @param list<array{media_object_id: string|int, sort_order: int}> $media */
    public function setMedia(Listing $listing, array $media): void
    {
        $listing->media()->detach();
        foreach ($media as $item) {
            $listing->media()->attach((int) $item['media_object_id'], ['sort_order' => (int) $item['sort_order']]);
        }
    }
}
