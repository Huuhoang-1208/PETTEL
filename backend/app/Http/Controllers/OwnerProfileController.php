<?php

namespace App\Http\Controllers;

use App\Models\MediaObject;
use App\Models\Booking;
use App\Http\Resources\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerProfileController extends Controller
{
    public function bookings(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $page = Booking::query()->where('owner_id', $request->user()->id)->latest('id')->paginate(20);
        return response()->json([
            'data' => $page->getCollection()->map(fn (Booking $booking) => [
                ...BookingResource::make($booking)->resolve($request),
                'participant_role' => 'OWNER',
                'listing_title' => $booking->privacy_redacted_at ? null : ($booking->listing_snapshot_json['title'] ?? null),
                'pet_name' => $booking->privacy_redacted_at ? null : ($booking->pet_snapshot_json['name'] ?? null),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->owner_profile_json ?? [];
        $bookings = DB::table('bookings')->where('owner_id', $user->id);

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
            'email_verified' => $user->email_verified_at !== null,
            'phone' => $profile['phone'] ?? null,
            'bio' => $profile['bio'] ?? null,
            'area_code' => $profile['area_code'] ?? null,
            'avatar_media_id' => isset($profile['avatar_media_id']) ? (string) $profile['avatar_media_id'] : null,
            'emergency_contacts' => $profile['emergency_contacts'] ?? null,
            'stats' => [
                'pet_count' => $user->pets()->where('status', 'ACTIVE')->count(),
                'booking_count' => (clone $bookings)->count(),
                'completed_count' => (clone $bookings)->where('status', 'COMPLETED')->count(),
            ],
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\S/u'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/\A[+0-9() .-]{6,30}\z/'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'emergency_contacts' => ['sometimes', 'array:primary,secondary,vet'],
            'emergency_contacts.primary' => ['sometimes', 'array:name,phone'],
            'emergency_contacts.secondary' => ['sometimes', 'array:name,phone,relationship,available_24h'],
            'emergency_contacts.vet' => ['sometimes', 'array:name,phone,doctor,address'],
            'emergency_contacts.*.name' => ['nullable', 'string', 'max:150'],
            'emergency_contacts.*.phone' => ['nullable', 'string', 'max:30', 'regex:/\A[+0-9() .-]{6,30}\z/'],
            'emergency_contacts.secondary.relationship' => ['nullable', 'string', 'max:80'],
            'emergency_contacts.secondary.available_24h' => ['sometimes', 'boolean'],
            'emergency_contacts.vet.doctor' => ['nullable', 'string', 'max:150'],
            'emergency_contacts.vet.address' => ['nullable', 'string', 'max:300'],
            'area_code' => ['nullable', Rule::in(['DN_CENTER', 'DN_NORTH', 'DN_SOUTH', 'DN_WEST', 'DN_EAST'])],
            'avatar_media_id' => ['nullable', 'integer', Rule::exists('media_objects', 'id')->where(fn ($query) => $query
                ->where('owner_user_id', $request->user()->id)->where('state', 'READY')->where('visibility', 'PRIVATE')
                ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
                ->whereNotIn('id', MediaObject::messageAttachmentIds()))],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $profile = $user->owner_profile_json ?? [];
            foreach (['phone', 'bio', 'area_code', 'avatar_media_id', 'emergency_contacts'] as $field) {
                if (array_key_exists($field, $data)) {
                    $profile[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            if (isset($data['name'])) $user->name = trim($data['name']);
            $user->owner_profile_json = $profile;
            $user->save();
        });
        $request->user()->refresh();

        return $this->show($request);
    }
}
