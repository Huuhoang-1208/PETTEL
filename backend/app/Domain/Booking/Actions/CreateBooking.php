<?php

namespace App\Domain\Booking\Actions;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBooking
{
    public function handle(StoreBookingRequest $request): Booking
    {
        $owner = $request->user();
        if (! $owner instanceof User) {
            abort(401);
        }
        $data = $request->validated();

        try {
            return DB::transaction(function () use ($request, $owner, $data): Booking {
                // Short database lock for consistent source reads, not a calendar hold.
                DB::table('listings')->where('id', $data['listing_id'])
                    ->lockForUpdate()->first(['id']);

                $context = $request->validateSources(lockForCreation: true);
                $listing = $context['listing'];
                $pricing = $context['pricing'];

                $booking = Booking::create([
                    'owner_id' => $owner->id,
                    'pet_id' => $context['pet']->id,
                    'listing_id' => $listing->id,
                    'caregiver_user_id' => $listing->caregiver_user_id,
                    'start_at' => $context['start'],
                    'end_at' => $context['end'],
                    'status' => 'REQUESTED',
                    'price_snapshot_vnd' => $pricing['price_total_vnd'],
                    'price_unit' => $pricing['price_unit'],
                    'price_quantity' => $pricing['quantity'],
                    'pricing_version' => $pricing['pricing_version'],
                    'cancellation_policy_version' => $pricing['cancellation_policy_version'],
                    'pet_snapshot_json' => $context['pet']->snapshotForBooking(),
                    'listing_snapshot_json' => [
                        'schema_version' => 1,
                        'listing_id' => (string) $listing->id,
                        'listing_version' => (int) $listing->version,
                        'title' => $listing->title,
                        'service_type' => $listing->service_type,
                        'area_code' => $listing->area_code,
                        'unit_price_vnd' => $pricing['unit_price_vnd'],
                        'price_unit' => $pricing['price_unit'],
                        'acceptance_rules' => json_decode($listing->acceptance_rules_json, true, 512, JSON_THROW_ON_ERROR),
                    ],
                    'policy_snapshot_json' => [
                        'schema_version' => 1,
                        'cancellation_policy_version' => $pricing['cancellation_policy_version'],
                        'cancellation_fee_vnd' => 0,
                        'payment_mode' => 'SANDBOX',
                    ],
                    'instructions_snapshot' => $data['instructions'] ?? null,
                    'payment_due_at' => null,
                    'current_payment_attempt_id' => null,
                    'request_idempotency_key' => $data['Idempotency-Key'],
                    'request_payload_hash' => $request->payloadHash(),
                    'version' => 1,
                ]);

                DB::table('booking_status_events')->insert([
                    'booking_id' => $booking->id,
                    'from_status' => null,
                    'to_status' => 'REQUESTED',
                    'actor_user_id' => $owner->id,
                    'source' => 'USER',
                    'reason_code' => null,
                    'reason_text' => null,
                    'created_at' => now()->utc()->format('Y-m-d H:i:s.u'),
                ]);

                return $booking->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (
                ! Booking::query()->where('owner_id', $owner->id)
                    ->where('request_idempotency_key', $data['Idempotency-Key'])->exists()
            ) {
                throw $exception;
            }

            // Task 5 will distinguish identical replay from a changed payload.
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'IDEMPOTENCY_CONFLICT',
                    'message' => 'This booking request key has already been used.',
                    'request_id' => (string) Str::uuid(),
                ],
            ], 409));
        }
    }
}
