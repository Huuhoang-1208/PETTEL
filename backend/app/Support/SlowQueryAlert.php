<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SlowQueryAlert
{
    public static function record(QueryExecuted $query): void
    {
        $thresholdMs = (float) config('performance.slow_query_alerts.threshold_ms', 1000);

        if ($query->time < $thresholdMs) {
            return;
        }

        $queryHash = hash('sha256', $query->sql);
        $cooldownSeconds = max(
            1,
            (int) config('performance.slow_query_alerts.cooldown_seconds', 300),
        );

        try {
            $shouldAlert = Cache::add(
                'monitoring:slow-query:'.$queryHash,
                true,
                $cooldownSeconds,
            );
        } catch (Throwable $exception) {
            Log::warning('Slow-query alert cooldown check failed.', [
                'exception' => $exception::class,
            ]);

            $shouldAlert = true;
        }

        if (! $shouldAlert) {
            return;
        }

        Log::critical('monitoring.slow_query_detected', [
            'connection' => $query->connectionName,
            'duration_ms' => round($query->time, 2),
            'threshold_ms' => $thresholdMs,
            'query_hash' => $queryHash,
        ]);
    }
}
