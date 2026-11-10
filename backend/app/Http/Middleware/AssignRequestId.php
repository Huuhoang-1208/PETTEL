<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerId = $request->header('X-Request-ID');

        $requestId = is_string($headerId) && Str::isUuid($headerId)
            ? $headerId
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        // Dùng cho log trong request và log của các job được dispatch từ request này.
        Log::withContext(['request_id' => $requestId]);
        Context::add('origin_request_id', $requestId);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            // Job đã được dispatch thì metadata đã được chụp lại.
            // Dọn context để không ảnh hưởng request tiếp theo.
            Context::forget('origin_request_id');
            Log::withoutContext();
        }
    }
}
