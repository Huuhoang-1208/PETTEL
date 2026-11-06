<?php

namespace App\Http\Requests;

use App\Domain\Booking\Actions\ApplyListingAvailability;
use App\Domain\Booking\Actions\CalculateBookingPrice;
use App\Domain\Listing\AcceptanceRules;
use App\Models\Listing;
use App\Models\Pet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;

class StoreBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // The header wins over a forged body field with the same name.
        $this->merge(['Idempotency-Key' => $this->header('Idempotency-Key')]);

        foreach (['pet_id', 'listing_id'] as $field) {
            $value = $this->input($field);
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $this->merge([$field => ltrim((string) $value, '0') ?: '0']);
            }
        }

        $instructions = $this->input('instructions');
        if (is_string($instructions)) {
            $normalized = Normalizer::normalize($instructions, Normalizer::FORM_C);
            $instructions = trim($normalized === false ? $instructions : $normalized);
        }
        $this->merge(['instructions' => $instructions === '' ? null : $instructions]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $timestamp = [
            'bail',
            'required',
            'string',
            'date',
            'regex:/\A\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-3]):[0-5]\d|[+-]14:00)\z/',
        ];

        return [
            'pet_id' => ['bail', 'required', 'string', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            'listing_id' => ['bail', 'required', 'string', 'regex:/\A[1-9][0-9]{0,19}\z/'],
            'start_at' => $timestamp,
            'end_at' => $timestamp,
            'instructions' => ['nullable', 'string', 'max:2000'],
            'Idempotency-Key' => ['required', 'string', 'regex:/\A[a-z0-9._:-]{1,128}\z/'],
        ];
    }

    // Call after replay handling and inside the create transaction in task 4/5.
    /** @return array{pet: Pet, listing: \stdClass, start: CarbonImmutable, end: CarbonImmutable, pricing: array<string, int|string>} */
    public function validateSources(bool $lockForCreation = false): array
    {
        $owner = $this->user();
        if (! $owner instanceof User) {
            $this->reject(401, 'UNAUTHENTICATED', 'Login required.');
        }

        if ($lockForCreation) {
            $owner = User::query()->whereKey($owner->id)->lockForUpdate()->first();
            if ($owner === null) {
                $this->reject(401, 'UNAUTHENTICATED', 'Login required.');
            }
            $ownerRole = DB::table('user_roles')->where('user_id', $owner->id)
                ->where('role', 'OWNER')->lockForUpdate()->first();
            if ($owner->status !== 'ACTIVE' || $ownerRole === null) {
                $this->reject(403, 'FORBIDDEN', 'An active owner account is required.');
            }
        }

        $data = $this->validated();
        $petQuery = Pet::query()->where('user_id', $owner->id)->whereKey($data['pet_id']);
        $pet = $lockForCreation ? $petQuery->lockForUpdate()->first() : $petQuery->first();
        if ($pet === null) {
            $this->reject(404, 'NOT_FOUND', 'Pet not found.');
        }
        if ($pet->status !== 'ACTIVE') {
            $this->reject(409, 'INVALID_STATE', 'Pet is not active.');
        }

        $listingQuery = DB::table('listings')
            ->join('caregiver_profiles as profiles', 'profiles.id', '=', 'listings.caregiver_profile_id')
            ->join('users as caregivers', 'caregivers.id', '=', 'profiles.user_id')
            ->where('listings.id', $data['listing_id'])
            ->whereNull('listings.deleted_at')
            ->select(
                'listings.*',
                'profiles.user_id as caregiver_user_id',
                'profiles.deleted_at as profile_deleted_at',
                'caregivers.status as caregiver_status'
            );
        $listing = $lockForCreation ? $listingQuery->lockForUpdate()->first() : $listingQuery->first();

        if ($listing === null) {
            $this->reject(404, 'NOT_FOUND', 'Listing not found.');
        }

        $selfBooking = (string) $listing->caregiver_user_id === (string) $owner->id;
        if ($listing->status !== 'ACTIVE') {
            if (! $selfBooking && ! $owner->hasRole('ADMIN')) {
                $this->reject(404, 'NOT_FOUND', 'Listing not found.');
            }
            $this->reject(409, 'INVALID_STATE', 'Listing is not active.');
        }

        $roleQuery = DB::table('user_roles')
            ->where('user_id', $listing->caregiver_user_id)->where('role', 'CAREGIVER');
        $hasCaregiverRole = $lockForCreation
            ? $roleQuery->lockForUpdate()->first() !== null
            : $roleQuery->exists();

        if ($listing->profile_deleted_at !== null || $listing->caregiver_status !== 'ACTIVE' || ! $hasCaregiverRole) {
            $this->reject(409, 'INVALID_STATE', 'Listing cannot receive bookings.');
        }

        if ($selfBooking) {
            $this->reject(422, 'VALIDATION_ERROR', 'Cannot book your own listing.', [
                'listing_id' => ['Choose a listing from another caregiver.'],
            ]);
        }

        $rules = AcceptanceRules::normalize(json_decode($listing->acceptance_rules_json, true));
        if ($rules === null) {
            $this->reject(409, 'INVALID_STATE', 'Listing conditions are invalid.');
        }

        if (! AcceptanceRules::accepts($rules, $pet->species, $pet->size)) {
            $this->reject(422, 'VALIDATION_ERROR', 'Pet does not match listing conditions.', [
                'pet_id' => ['Pet species or size is not accepted.'],
            ]);
        }

        $start = CarbonImmutable::parse($data['start_at'])->utc();
        $end = CarbonImmutable::parse($data['end_at'])->utc();

        if ($start->lessThanOrEqualTo(CarbonImmutable::now())) {
            $this->reject(422, 'VALIDATION_ERROR', 'Start must be in the future.', [
                'start_at' => ['Choose a future start time.'],
            ]);
        }

        $pricing = app(CalculateBookingPrice::class)->calculate(
            $listing->service_type,
            $listing->price_unit,
            $listing->price_vnd,
            $start,
            $end,
        );
        $available = app(ApplyListingAvailability::class)->apply(
            Listing::query()->whereKey($listing->id),
            $start,
            $end,
        )->exists();

        if (! $available) {
            $this->reject(409, 'AVAILABILITY_CONFLICT', 'Time range is unavailable.');
        }

        return [
            'pet' => $pet,
            'listing' => $listing,
            'start' => $start,
            'end' => $end,
            'pricing' => $pricing,
        ];
    }

    public function payloadHash(): string
    {
        $data = $this->validated();
        $payload = [
            'pet_id' => (string) $data['pet_id'],
            'listing_id' => (string) $data['listing_id'],
            'start_at' => CarbonImmutable::parse($data['start_at'])->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => CarbonImmutable::parse($data['end_at'])->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'instructions' => $data['instructions'] ?? null,
        ];

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject(422, 'VALIDATION_ERROR', 'Invalid booking input.', $validator->errors()->toArray());
    }

    /** @param array<string, mixed> $details */
    private function reject(int $status, string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
