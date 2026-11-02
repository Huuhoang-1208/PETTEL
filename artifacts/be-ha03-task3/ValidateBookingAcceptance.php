<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ValidateBookingAcceptance
{
    public function handle(User $caregiver, string $id): Booking
    {
        // Task 3 only validates. Task 4 must lock current DB rows before using it.
        $currentCaregiver = User::query()->find($caregiver->id);
        abort_if($currentCaregiver === null, 401);
        abort_unless(
            $currentCaregiver->status === 'ACTIVE' && $currentCaregiver->hasRole('CAREGIVER'),
            403,
        );

        $booking = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $currentCaregiver->id)->first();

        if ($booking === null) {
            $this->fail('NOT_FOUND', 'Booking not found.', 404);
        }
        if ($booking->status !== 'REQUESTED') {
            $this->fail('INVALID_STATE', 'Only REQUESTED bookings can be accepted.');
        }

        $now = CarbonImmutable::now('UTC');
        if (
            $now->greaterThanOrEqualTo($booking->created_at->addHours(24))
            || $now->greaterThanOrEqualTo($booking->start_at)
        ) {
            $this->fail('REQUEST_EXPIRED', 'This booking request has expired.');
        }

        $listing = Listing::query()->with('caregiverProfile')
            ->whereKey($booking->listing_id)->first();

        if (
            $listing === null
            || $listing->status !== 'ACTIVE'
            || $listing->caregiverProfile === null
            || (string) $listing->caregiverProfile->user_id !== (string) $currentCaregiver->id
        ) {
            $this->fail('INVALID_STATE', 'Listing cannot receive bookings.');
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = $booking->listing_snapshot_json;
        if (
            $listing->service_type !== ($snapshot['service_type'] ?? null)
            || $listing->price_unit !== ($snapshot['price_unit'] ?? null)
            || $this->normalizeRules($listing->acceptance_rules_json)
                !== $this->normalizeRules($snapshot['acceptance_rules'] ?? null)
        ) {
            $this->fail('INVALID_STATE', 'Listing conditions changed. Create a new booking.');
        }

        $available = app(ApplyListingAvailability::class)->apply(
            Listing::query()->whereKey($listing->id),
            $booking->start_at,
            $booking->end_at,
        )->exists();

        if (! $available) {
            $this->fail('AVAILABILITY_CONFLICT', 'Time range is unavailable.');
        }

        return $booking;
    }

    /** @return array{schema_version: int, species: list<string>, sizes: list<string>} */
    private function normalizeRules(mixed $rules): array
    {
        if (! is_array($rules) || ($rules['schema_version'] ?? null) !== 1) {
            $this->fail('INVALID_STATE', 'Listing conditions are invalid.');
        }

        $validator = validator($rules, [
            'species' => ['required', 'array', 'list', 'min:1'],
            'species.*' => ['required', 'string', Rule::in(['DOG', 'CAT', 'OTHER'])],
            'sizes' => ['required', 'array', 'list', 'min:1'],
            'sizes.*' => ['required', 'string', Rule::in(['SMALL', 'MEDIUM', 'LARGE'])],
        ]);
        if ($validator->fails()) {
            $this->fail('INVALID_STATE', 'Listing conditions are invalid.');
        }

        $species = array_values(array_unique($rules['species']));
        $sizes = array_values(array_unique($rules['sizes']));
        sort($species);
        sort($sizes);

        return ['schema_version' => 1, 'species' => $species, 'sizes' => $sizes];
    }

    private function fail(string $code, string $message, int $status = 409): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
