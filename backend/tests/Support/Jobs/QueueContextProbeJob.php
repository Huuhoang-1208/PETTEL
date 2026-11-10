<?php

namespace Tests\Support\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class QueueContextProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $failuresBeforeSuccess = 0,
    ) {}

    public function handle(): void
    {
        if ($this->attempts() <= $this->failuresBeforeSuccess) {
            // Dùng lỗi giả lập, không đưa dữ liệu request vào exception.
            throw new RuntimeException('Synthetic queue failure.');
        }

        Log::channel('queue_context_test')->info('queue_context_probe.completed');
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('queue_context_test')->error('queue_context_probe.failed', [
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }
}
