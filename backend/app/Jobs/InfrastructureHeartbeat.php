<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class InfrastructureHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put(
            'infra:heartbeat',
            now()->toIso8601String(),
            180,
        );
    }
}
