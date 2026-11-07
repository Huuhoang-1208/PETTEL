<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;

class StoreBookingReviewController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof User, 401);

        try {
            $review = DB::transaction(function () use ($request, $owner, $id): array {
                $currentOwner = User::query()->whereKey($owner->id)->lockForUpdate()->first();
                abort_if($currentOwner === null, 401);

                $role = DB::table('user_roles')->where('user_id', $owner->id)
                    ->where('role', 'OWNER')->lockForUpdate()->first();
                abort_unless($currentOwner->status === 'ACTIVE' && $role !== null, 403);

                $booking = Booking::query()->whereKey($id)
                    ->where('owner_id', $owner->id)->lockForUpdate()->first();

                if ($booking === null) {
                    $this->fail(404, 'NOT_FOUND', 'Booking not found.');
                }

                if ((string) $booking->caregiver_user_id === (string) $owner->id) {
                    $this->fail(403, 'FORBIDDEN', 'You cannot review your own service.');
                }

                if ($booking->status !== 'COMPLETED') {
                    $this->fail(409, 'INVALID_STATE', 'Only COMPLETED bookings can be reviewed.');
                }

                $input = $request->all();
                $unknown = array_diff(array_keys($input), ['rating', 'body']);

                if ($unknown !== []) {
                    $this->fail(
                        422,
                        'VALIDATION_ERROR',
                        'The given data was invalid.',
                        array_fill_keys($unknown, ['This field is not accepted.'])
                    );
                }

                if (is_string($input['body'] ?? null)) {
                    $body = Str::trim($input['body']);
                    $normalized = Normalizer::normalize($body, Normalizer::FORM_C);
                    $body = $normalized === false ? $body : $normalized;
                    $input['body'] = $body === '' ? null : $body;
                }

                $validator = validator($input, [
                    'rating' => ['bail', 'required', 'integer:strict', 'between:1,5'],
                    'body' => ['nullable', 'string', 'max:1000'],
                ]);

                if ($validator->fails()) {
                    $this->fail(
                        422,
                        'VALIDATION_ERROR',
                        'The given data was invalid.',
                        $validator->errors()->toArray()
                    );
                }

                $data = $validator->validated();

                if (DB::table('reviews')->where('booking_id', $booking->id)->exists()) {
                    $this->fail(409, 'REVIEW_ALREADY_EXISTS', 'This booking already has a review.');
                }

                $now = now('UTC');

                $reviewId = DB::table('reviews')->insertGetId([
                    'booking_id' => $booking->id,
                    'owner_id' => $booking->owner_id,
                    'listing_id' => $booking->listing_id,
                    'rating' => $data['rating'],
                    'body' => $data['body'] ?? null,
                    'status' => 'VISIBLE',
                    'is_demo' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'id' => (string) $reviewId,
                    'booking_id' => (string) $booking->id,
                    'listing_id' => (string) $booking->listing_id,
                    'rating' => (int) $data['rating'],
                    'body' => $data['body'] ?? null,
                    'status' => 'VISIBLE',
                    'is_demo' => false,
                    'created_at' => $now->format('Y-m-d\TH:i:s.u\Z'),
                ];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! DB::table('reviews')->useWritePdo()->where('booking_id', $id)->exists()) {
                throw $exception;
            }

            $this->fail(409, 'REVIEW_ALREADY_EXISTS', 'This booking already has a review.');
        }

        return response()->json(['data' => $review], 201);
    }

    /** @param array<string, mixed> $details */
    private function fail(int $status, string $code, string $message, array $details = []): never
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
