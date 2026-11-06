<?php

namespace App\Domain\Booking\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class BookingCancellationException extends HttpResponseException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct(response()->json([
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'details' => [],
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
