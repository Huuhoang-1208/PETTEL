<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class BookingController extends Controller
{
    public function quote(QuoteBookingRequest $request): JsonResponse
    {
        $context = $request->validateSources();

        return response()->json(Arr::except($context['pricing'], ['unit_price_vnd']));
    }

    public function store(StoreBookingRequest $request): Response
    {
        // This also calculates the price using the same action as quote.
        $request->validateSources();

        // Task 4 will persist the booking and its snapshots.
        return response()->noContent();
    }
}
