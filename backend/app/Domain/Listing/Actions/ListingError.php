<?php

namespace App\Domain\Listing\Actions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ListingError
{
    /** @param array<string, mixed> $details */
    public static function fail(int $status, string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details,
            'request_id' => (string) Str::uuid(),
        ]], $status));
    }
}
