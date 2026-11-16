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

        $average = $this->getAttributes()['rating_average'] ?? null;
        $caregiver = $this->whenLoaded('caregiverProfile', function (): array {
            $profile = $this->caregiverProfile;
            $demo = $profile->demo_profile_json ?? [];
            $application = $profile->application_data_json ?? [];

            return [
                'display_name' => $profile->display_name,
                'bio' => $profile->bio,
                'type' => $profile->type,
                'area_code' => $profile->area_code,
                'name' => $profile->user?->name,
                'experience_years' => isset($application['experience_years'])
                    ? (int) $application['experience_years']
                    : null,
                'care_principles' => $application['care_principles'] ?? [],
                'pet_requirements' => $application['pet_requirements'] ?? [],
                'demo' => $demo === [] ? null : [
                    'home_type' => $demo['home_type'] ?? null,
                    'photo_file' => $demo['photo_file'] ?? null,
                    'status' => $demo['status'] ?? null,
                    'amenities' => $demo['amenities'] ?? [],
                    'trust_level' => $demo['trust_level'] ?? null,
                    'confidence_score' => $demo['confidence_score'] ?? null,
                    'verified_details' => $demo['verified_details'] ?? [],
                    'rating_average' => $demo['rating_average'] ?? null,
                    'review_count' => $demo['review_count'] ?? null,
                    'introduction' => $demo['introduction'] ?? null,
                    'experience_years' => $demo['experience_years'] ?? null,
                    'care_principles' => $demo['care_principles'] ?? [],
                    'space_amenities' => $demo['space_amenities'] ?? [],
                    'is_demo' => true,
                ],
            ];
        });

        $demo = $this->caregiverProfile?->demo_profile_json ?? [];

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'service_type' => $this->service_type,
            'area_code' => $this->area_code,
            'price_vnd' => $this->price_vnd,
            'price_unit' => $this->price_unit,
            'acceptance_rules_json' => $this->acceptance_rules_json,
            'caregiver' => $caregiver,
            'media' => $this->whenLoaded('media', fn () => $this->media->filter(
                fn ($media): bool => $media->state === 'READY' && $media->visibility === 'PUBLIC_ELIGIBLE'
            )->map(fn ($media): array => [
                'media_object_id' => (string) $media->id,
                'sort_order' => (int) $media->pivot->sort_order,
                'image_url' => route('listings.media.content', ['id' => $this->id, 'mediaId' => $media->id]),
                'thumbnail_url' => route('listings.media.content', ['id' => $this->id, 'mediaId' => $media->id, 'variant' => 'thumbnail']),
            ])->values()),
            'price_total_vnd' => $total === null ? null : (int) $total,
            'rating_average' => $average === null
                ? (isset($demo['rating_average']) ? (float) $demo['rating_average'] : null)
                : round((float) $average, 2),
            'review_count' => $average === null
                ? (int) ($demo['review_count'] ?? 0)
                : (int) ($this->getAttributes()['review_count'] ?? 0),
        ];
    }
}
