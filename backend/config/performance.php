<?php

return [
    'enabled' => env('APP_PERFORMANCE_METRICS', false),

    'slow_query_alerts' => [
        'enabled' => env('SLOW_QUERY_ALERTS_ENABLED', false),
        'threshold_ms' => (float) env('SLOW_QUERY_THRESHOLD_MS', 1000),
        'cooldown_seconds' => (int) env('SLOW_QUERY_ALERT_COOLDOWN_SECONDS', 300),
    ],

    'server_alerts' => [
        'enabled' => env('SERVER_RESOURCE_ALERTS_ENABLED', false),
        'cpu_percent' => (float) env('SERVER_CPU_ALERT_THRESHOLD', 85),
        'memory_percent' => (float) env('SERVER_MEMORY_ALERT_THRESHOLD', 85),
        'disk_percent' => (float) env('SERVER_DISK_ALERT_THRESHOLD', 90),
        'network_bytes_per_minute' => (float) env(
            'SERVER_NETWORK_ALERT_BYTES_PER_MINUTE',
            104857600,
        ),
        'network_interface' => env('SERVER_NETWORK_INTERFACE', 'eth0'),
        'cooldown_seconds' => (int) env('SERVER_RESOURCE_ALERT_COOLDOWN_SECONDS', 900),
    ],
];
