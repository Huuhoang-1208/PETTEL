<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\CreateBooking;
use App\Http\Requests\QuoteBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class BookingController extends Controller
{
    /**
     * @response array{price_total_vnd: int, price_unit: string, quantity: int, pricing_version: int, cancellation_policy_version: int}
     */
    public function quote(QuoteBookingRequest $request): JsonResponse
    {
        $context = $request->validateSources();

        return response()->json(Arr::except($context['pricing'], ['unit_price_vnd']));
    }

    /**
     * @response array{id: string, status: string, pet_id: string, listing_id: string, start_at: string, end_at: string, price_snapshot_vnd: int, payment_due_at: ?string}
     */
    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $booking = $createBooking->handle($request);

        return response()->json(
            BookingResource::make($booking)->resolve($request),
            $booking->wasRecentlyCreated ? 201 : 200,
        );
    }
}
