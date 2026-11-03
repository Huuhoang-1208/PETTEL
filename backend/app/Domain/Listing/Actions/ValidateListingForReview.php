<?php

namespace App\Domain\Listing\Actions;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ValidateListingForReview
{
    public function caregiver(Listing $listing): User
    {
        $profile = $listing->caregiverProfile()->first();
        $caregiver = $profile?->user;
        if ($profile === null || $caregiver === null || $caregiver->status !== 'ACTIVE' || ! $caregiver->hasRole('CAREGIVER')) {
            ListingError::fail(409, 'INVALID_STATE', 'Caregiver cannot publish this listing.');
        }

        return $caregiver;
    }

    public function listing(Listing $listing, User $caregiver, int $failureStatus = 409): void
    {
        $payload = $listing->only(['title', 'description', 'service_type', 'area_code', 'price_vnd', 'price_unit']);
        $payload['acceptance_rules_json'] = $listing->acceptance_rules_json;
        $payload['media'] = DB::table('listing_media')->where('listing_id', $listing->id)
            ->orderBy('sort_order')->get(['media_object_id', 'sort_order'])
            ->map(fn ($media): array => [
                'media_object_id' => (string) $media->media_object_id,
                'sort_order' => (int) $media->sort_order,
            ])->all();

        $this->payload($payload, $caregiver, $failureStatus);
    }

    /** @param array<string, mixed> $payload */
    public function payload(array $payload, User $caregiver, int $failureStatus = 422): void
    {
        $validator = validator($payload, [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'service_type' => ['required', 'in:DAYCARE,BOARDING'],
            'area_code' => ['required', 'in:DN_CENTER,DN_NORTH,DN_SOUTH,DN_WEST,DN_EAST'],
            'price_vnd' => ['required', 'integer:strict', 'min:1'],
            'price_unit' => ['required', 'in:HOUR,NIGHT'],
            'acceptance_rules_json' => ['required', 'array:schema_version,species,sizes'],
            'acceptance_rules_json.schema_version' => ['required', 'integer:strict', 'in:1'],
            'acceptance_rules_json.species' => ['required', 'array', 'list', 'min:1'],
            'acceptance_rules_json.species.*' => ['string', 'in:DOG,CAT,OTHER'],
            'acceptance_rules_json.sizes' => ['required', 'array', 'list', 'min:1'],
            'acceptance_rules_json.sizes.*' => ['string', 'in:SMALL,MEDIUM,LARGE'],
            'media' => ['present', 'array', 'max:10', 'list'],
            'media.*' => ['array:media_object_id,sort_order'],
            'media.*.media_object_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/'],
            'media.*.sort_order' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        if ($validator->fails()) {
            $this->fail($failureStatus, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        if (trim($data['title']) === '' || trim($data['description']) === '') {
            $this->fail($failureStatus, ['title' => ['Title and description must not be blank.']]);
        }
        if (! in_array([$data['service_type'], $data['price_unit']], [['DAYCARE', 'HOUR'], ['BOARDING', 'NIGHT']], true)) {
            $this->fail($failureStatus, ['price_unit' => ['Use DAYCARE/HOUR or BOARDING/NIGHT.']]);
        }

        $items = $data['media'];
        if (count(array_unique(array_column($items, 'media_object_id'))) !== count($items)
            || array_column($items, 'sort_order') !== ($items === [] ? [] : range(1, count($items)))) {
            $this->fail($failureStatus, ['media' => ['IDs must be unique and order must be contiguous.']]);
        }

        $ids = array_column($items, 'media_object_id');
        if ($ids !== []) {
            $readyOwnedCount = DB::table('media_objects')->whereIn('id', $ids)
                ->where('state', 'READY')->where('owner_user_id', $caregiver->id)->count();
            $privateReferences = DB::table('pets')->whereIn('photo_media_id', $ids)->exists()
                || DB::table('care_updates')->whereIn('media_object_id', $ids)->exists();
            if ($readyOwnedCount !== count($ids) || $privateReferences) {
                $this->fail($failureStatus, ['media' => ['Images must be ready, owned by this caregiver, and unused by private content.']]);
            }
        }
    }

    /** @param array<string, array<int, string>> $details */
    private function fail(int $status, array $details): never
    {
        $code = $status === 422 ? 'VALIDATION_ERROR' : 'INVALID_STATE';
        $message = $status === 422 ? 'Invalid listing content.' : 'Listing no longer satisfies review requirements.';
        ListingError::fail($status, $code, $message, $details);
    }
}
