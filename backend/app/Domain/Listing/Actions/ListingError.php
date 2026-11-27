<?php

namespace App\Domain\Listing\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ListingError
{
    /** @param array<string, mixed> $details */
    public static function fail(int $status, string $code, string $message, array $details = []): never
    {
        $requestId = request()->attributes->get('request_id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
            ],
        ], $status));
    }
}
