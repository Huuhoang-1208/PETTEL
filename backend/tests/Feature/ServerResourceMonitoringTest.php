<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ServerResourceMonitoringTest extends TestCase
{
    public function test_command_does_not_alert_when_monitoring_is_disabled(): void
    {
        config(['performance.server_alerts.enabled' => false]);
        Log::spy();

        $this->artisan('monitoring:check-server-resources')
            ->assertSuccessful();

        Log::shouldNotHaveReceived('critical');
    }

    public function test_command_measures_all_resources_and_suppresses_repeat_alerts(): void
    {
        config([
            'performance.server_alerts.enabled' => true,
            'performance.server_alerts.cpu_percent' => 0,
            'performance.server_alerts.memory_percent' => 0,
            'performance.server_alerts.disk_percent' => 0,
            'performance.server_alerts.network_bytes_per_minute' => 0,
            'performance.server_alerts.network_interface' => 'eth0',
            'performance.server_alerts.cooldown_seconds' => 900,
        ]);

        foreach ([
            'cpu_percent',
            'memory_percent',
            'disk_percent',
            'network_bytes_per_minute',
        ] as $metric) {
            Cache::forget('monitoring:server-alert:'.$metric);
        }

        // Baseline để lần chạy test đầu đo được CPU và network ngay.
        Cache::put('monitoring:server:cpu', [
            'total' => 0,
            'idle' => 0,
        ], 300);

        Cache::put('monitoring:server:network', [
            'received' => 0,
            'transmitted' => 0,
            'sampled_at' => microtime(true) - 60,
        ], 300);

        Log::spy();

        $this->artisan('monitoring:check-server-resources')
            ->assertSuccessful();

        $this->artisan('monitoring:check-server-resources')
            ->assertSuccessful();

        Log::shouldHaveReceived('critical')
            ->times(4)
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'monitoring.server_resource_threshold_exceeded'
                    && in_array($context['metric'] ?? null, [
                        'cpu_percent',
                        'memory_percent',
                        'disk_percent',
                        'network_bytes_per_minute',
                    ], true)
                    && array_key_exists('value', $context)
                    && array_key_exists('threshold', $context)
                    && array_key_exists('unit', $context);
            });
    }
}
