<?php

namespace App\Http\Middleware;

use App\Support\PerformanceQueryRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPerformanceQueryMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('performance.enabled') || ! app()->environment('performance')) {
            return $next($request);
        }

        PerformanceQueryRecorder::startRequest();
        $response = $next($request);
        $route = $request->route();
        $record = [
            'time' => now()->utc()->toIso8601String(),
            'method' => $request->method(),
            'route' => $route?->uri() ?? 'unmatched',
            'status' => $response->getStatusCode(),
            ...PerformanceQueryRecorder::snapshot(),
        ];
        file_put_contents(
            storage_path('logs/performance-queries.jsonl'),
            json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );

        return $response;
    }
}
