<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\CaregiverProfile;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class ValidateBookingAcceptance
{
    public function handle(User $caregiver, string $id, bool $lockForAcceptance = false): Booking
    {
        if ($lockForAcceptance && DB::transactionLevel() === 0) {
            throw new LogicException('Accept validation requires a transaction.');
        }

        // Only locate the listing here. All decisions below use fresh locked reads.
        $locator = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $caregiver->id)->first(['listing_id']);
        if ($locator === null) {
            $this->fail('NOT_FOUND', 'Booking not found.', 404);
        }

        $listings = Listing::withTrashed()->whereKey($locator->listing_id);
        if ($lockForAcceptance) {
            $listings->lockForUpdate();
        }
        $listing = $listings->first();

        $users = User::query()->whereKey($caregiver->id);
        $roles = DB::table('user_roles')->where('user_id', $caregiver->id)
            ->where('role', 'CAREGIVER');
        if ($lockForAcceptance) {
            $users->sharedLock();
            $roles->sharedLock();
        }
        $currentCaregiver = $users->first();
        abort_if($currentCaregiver === null, 401);
        abort_unless($currentCaregiver->status === 'ACTIVE' && $roles->first() !== null, 403);

        $bookings = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $currentCaregiver->id);
        if ($lockForAcceptance) {
            $bookings->lockForUpdate();
        }
        $booking = $bookings->first();
        if ($booking === null) {
            $this->fail('NOT_FOUND', 'Booking not found.', 404);
        }
        if ($booking->status !== 'REQUESTED') {
            $this->fail('INVALID_STATE', 'Only REQUESTED bookings can be accepted.');
        }
        $this->ensureNotExpired($booking, CarbonImmutable::now('UTC'));

        if (
            $listing === null || $listing->trashed() || $listing->status !== 'ACTIVE'
            || (string) $booking->listing_id !== (string) $listing->id
        ) {
            $this->fail('INVALID_STATE', 'Listing cannot receive bookings.');
        }
        $profiles = CaregiverProfile::query()->whereKey($listing->caregiver_profile_id);
        if ($lockForAcceptance) {
            $profiles->sharedLock();
        }
        $profile = $profiles->first();
        if ($profile === null || (string) $profile->user_id !== (string) $currentCaregiver->id) {
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

        if (! app(ApplyListingAvailability::class)->isAvailableFor($booking, $lockForAcceptance)) {
            $this->fail('AVAILABILITY_CONFLICT', 'Time range is unavailable.');
        }

        return $booking;
    }

    public function ensureNotExpired(Booking $booking, CarbonImmutable $at): void
    {
        if (
            $at->greaterThanOrEqualTo($booking->created_at->addHours(24))
            || $at->greaterThanOrEqualTo($booking->start_at)
        ) {
            $this->fail('REQUEST_EXPIRED', 'This booking request has expired.');
        }
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
