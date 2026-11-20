<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MonitorServerResources extends Command
{
    protected $signature = 'monitoring:check-server-resources';

    protected $description = 'Check server resource usage against configured thresholds';

    public function handle(): int
    {
        if (! config('performance.server_alerts.enabled')) {
            return self::SUCCESS;
        }

        $metrics = [
            'cpu_percent' => $this->cpuPercent(),
            'memory_percent' => $this->memoryPercent(),
            'disk_percent' => $this->diskPercent(),
            'network_bytes_per_minute' => $this->networkBytesPerMinute(),
        ];

        $rules = [
            'cpu_percent' => [
                (float) config('performance.server_alerts.cpu_percent', 85),
                '%',
            ],
            'memory_percent' => [
                (float) config('performance.server_alerts.memory_percent', 85),
                '%',
            ],
            'disk_percent' => [
                (float) config('performance.server_alerts.disk_percent', 90),
                '%',
            ],
            'network_bytes_per_minute' => [
                (float) config('performance.server_alerts.network_bytes_per_minute', 104857600),
                'bytes/min',
            ],
        ];

        $cooldownSeconds = max(
            1,
            (int) config('performance.server_alerts.cooldown_seconds', 900),
        );

        foreach ($rules as $metric => [$threshold, $unit]) {
            $value = $metrics[$metric];

            if ($value === null) {
                continue;
            }

            $cacheKey = 'monitoring:server-alert:'.$metric;

            if ($value < $threshold) {
                try {
                    Cache::forget($cacheKey);
                } catch (Throwable) {
                    // Monitoring must not interrupt application work.
                }

                continue;
            }

            try {
                $shouldAlert = Cache::add($cacheKey, true, $cooldownSeconds);
            } catch (Throwable $exception) {
                Log::warning('Server-alert cooldown check failed.', [
                    'exception' => $exception::class,
                    'metric' => $metric,
                ]);

                $shouldAlert = true;
            }

            if (! $shouldAlert) {
                continue;
            }

            Log::critical('monitoring.server_resource_threshold_exceeded', [
                'metric' => $metric,
                'value' => round($value, 2),
                'threshold' => $threshold,
                'unit' => $unit,
            ]);

            $this->warn(sprintf(
                '%s is %.2f %s; threshold is %.2f %s.',
                $metric,
                $value,
                $unit,
                $threshold,
                $unit,
            ));
        }

        return self::SUCCESS;
    }

    private function cpuPercent(): ?float
    {
        $stat = @file_get_contents('/proc/stat');

        if (! is_string($stat) || preg_match('/^cpu\s+(.+)$/m', $stat, $matches) !== 1) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($matches[1]));

        if (! is_array($parts) || count($parts) < 4) {
            return null;
        }

        $counters = array_map(static fn (string $part): int => (int) $part, $parts);
        $total = array_sum($counters);
        $idle = $counters[3] + ($counters[4] ?? 0);
        $previous = Cache::get('monitoring:server:cpu');

        Cache::put('monitoring:server:cpu', [
            'total' => $total,
            'idle' => $idle,
        ], 300);

        if (
            ! is_array($previous)
            || ! isset($previous['total'], $previous['idle'])
            || ! is_numeric($previous['total'])
            || ! is_numeric($previous['idle'])
        ) {
            return null;
        }

        $totalDelta = $total - (int) $previous['total'];
        $idleDelta = $idle - (int) $previous['idle'];

        if ($totalDelta <= 0) {
            return null;
        }

        return round(max(0, min(100, (($totalDelta - $idleDelta) / $totalDelta) * 100)), 2);
    }

    private function memoryPercent(): ?float
    {
        $meminfo = @file_get_contents('/proc/meminfo');

        if (
            ! is_string($meminfo)
            || preg_match('/^MemTotal:\s+(\d+)\s+kB$/m', $meminfo, $totalMatch) !== 1
            || preg_match('/^MemAvailable:\s+(\d+)\s+kB$/m', $meminfo, $availableMatch) !== 1
        ) {
            return null;
        }

        $total = (int) $totalMatch[1];
        $available = (int) $availableMatch[1];

        if ($total <= 0) {
            return null;
        }

        return round((($total - $available) / $total) * 100, 2);
    }

    private function diskPercent(): ?float
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');

        if (! is_float($total)) {
            return null;
        }

        if (! is_float($free) || $total <= 0) {
            return null;
        }

        return round((($total - $free) / $total) * 100, 2);
    }

    private function networkBytesPerMinute(): ?float
    {
        $interface = (string) config('performance.server_alerts.network_interface', 'eth0');

        if (preg_match('/^[A-Za-z0-9_.:-]+$/', $interface) !== 1) {
            return null;
        }

        $basePath = '/sys/class/net/'.$interface.'/statistics/';
        $received = @file_get_contents($basePath.'rx_bytes');
        $transmitted = @file_get_contents($basePath.'tx_bytes');

        if (
            ! is_string($received)
            || ! is_string($transmitted)
            || ! is_numeric(trim($received))
            || ! is_numeric(trim($transmitted))
        ) {
            return null;
        }

        $now = microtime(true);
        $current = [
            'received' => (int) trim($received),
            'transmitted' => (int) trim($transmitted),
            'sampled_at' => $now,
        ];
        $previous = Cache::get('monitoring:server:network');

        Cache::put('monitoring:server:network', $current, 300);

        if (
            ! is_array($previous)
            || ! isset($previous['received'], $previous['transmitted'], $previous['sampled_at'])
            || ! is_numeric($previous['received'])
            || ! is_numeric($previous['transmitted'])
            || ! is_numeric($previous['sampled_at'])
        ) {
            return null;
        }

        $elapsedSeconds = $now - (float) $previous['sampled_at'];

        if ($elapsedSeconds <= 0) {
            return null;
        }

        $byteDelta = max(
            0,
            $current['received']
            + $current['transmitted']
            - (int) $previous['received']
            - (int) $previous['transmitted'],
        );

        return round(($byteDelta / $elapsedSeconds) * 60, 2);
    }
}
