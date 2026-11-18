<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingQueryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $page = $this->accessible($request)->latest('id')->paginate($input['per_page'] ?? 20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Booking $booking) => $this->resource($request, $booking)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->accessible($request)->findOrFail($id);
        $data = $this->resource($request, $booking);
        $data['cancellation_reason_code'] = $booking->status === 'CANCELLED'
            ? DB::table('booking_status_events')->where('booking_id', $booking->id)
                ->where('to_status', 'CANCELLED')->orderByDesc('id')->value('reason_code')
            : null;

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return Builder<Booking> */
    private function accessible(Request $request): Builder
    {
        return Booking::query()->where(fn (Builder $query) => $query
            ->where('owner_id', $request->user()->id)
            ->orWhere('caregiver_user_id', $request->user()->id));
    }

    /** @return array<string, mixed> */
    private function resource(Request $request, Booking $booking): array
    {
        $redacted = $booking->privacy_redacted_at !== null;

        return [
            ...BookingResource::make($booking)->resolve($request),
            'participant_role' => (string) $booking->owner_id === (string) $request->user()->id ? 'OWNER' : 'CAREGIVER',
            'listing_title' => $redacted ? null : ($booking->listing_snapshot_json['title'] ?? null),
            'pet_name' => $redacted ? null : ($booking->pet_snapshot_json['name'] ?? null),
        ];
    }
}
