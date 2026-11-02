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
    public function quote(QuoteBookingRequest $request): JsonResponse
    {
        $context = $request->validateSources();

        return response()->json(Arr::except($context['pricing'], ['unit_price_vnd']));
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): JsonResponse
    {
        $booking = $createBooking->handle($request);

        return response()->json(
            BookingResource::make($booking)->resolve($request),
            $booking->wasRecentlyCreated ? 201 : 200,
        );
    }
}
