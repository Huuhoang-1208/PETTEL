<?php

namespace Tests\Feature;

use App\Support\SlowQueryAlert;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SlowQueryAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'performance.slow_query_alerts.threshold_ms' => 1000,
            'performance.slow_query_alerts.cooldown_seconds' => 300,
        ]);
    }

    public function test_query_below_threshold_does_not_alert(): void
    {
        Cache::spy();
        Log::spy();

        SlowQueryAlert::record($this->queryEvent(999));

        Cache::shouldNotHaveReceived('add');
        Log::shouldNotHaveReceived('critical');
    }

    public function test_query_at_or_above_threshold_logs_safe_context(): void
    {
        $sql = 'select * from users where email = ?';
        $secretBinding = 'private@example.test';
        $hash = hash('sha256', $sql);

        Cache::shouldReceive('add')
            ->twice()
            ->with('monitoring:slow-query:'.$hash, true, 300)
            ->andReturn(true);

        Log::spy();

        SlowQueryAlert::record($this->queryEvent(1000, $sql, [$secretBinding]));
        SlowQueryAlert::record($this->queryEvent(1250, $sql, [$secretBinding]));

        Log::shouldHaveReceived('critical')
            ->twice()
            ->withArgs(function (string $message, array $context) use ($hash, $secretBinding): bool {
                $serializedContext = json_encode($context);

                return $message === 'monitoring.slow_query_detected'
                    && $context['duration_ms'] >= 1000
                    && $context['threshold_ms'] === 1000.0
                    && $context['query_hash'] === $hash
                    && ! str_contains($serializedContext, $secretBinding)
                    && ! array_key_exists('sql', $context)
                    && ! array_key_exists('bindings', $context);
            });
    }

    public function test_query_is_suppressed_when_inside_cooldown(): void
    {
        $sql = 'select * from users where id = ?';
        $hash = hash('sha256', $sql);

        Cache::shouldReceive('add')
            ->once()
            ->with('monitoring:slow-query:'.$hash, true, 300)
            ->andReturn(false);

        Log::spy();

        SlowQueryAlert::record($this->queryEvent(1200, $sql, [42]));

        Log::shouldNotHaveReceived('critical');
    }

    public function test_cache_failure_is_logged_and_query_alert_still_fires(): void
    {
        Cache::shouldReceive('add')
            ->once()
            ->andThrow(new RuntimeException('cache unavailable'));

        Log::spy();

        SlowQueryAlert::record($this->queryEvent(1200));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(
                fn (string $message, array $context): bool => $message === 'Slow-query alert cooldown check failed.'
                    && $context['exception'] === RuntimeException::class
            );

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(
                fn (string $message): bool => $message === 'monitoring.slow_query_detected'
            );
    }

    /**
     * Tạo sự kiện giả, không thực thi truy vấn thật.
     *
     * @param  array<int, mixed>  $bindings
     */
    private function queryEvent(
        float $time,
        string $sql = 'select * from users where email = ?',
        array $bindings = ['private@example.test'],
    ): QueryExecuted {
        return new QueryExecuted($sql, $bindings, $time, DB::connection());
    }
}
