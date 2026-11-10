<?php

namespace Tests\Feature;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\Jobs\QueueContextProbeJob;
use Tests\TestCase;

class QueueContextPropagationTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml đặt queue=sync; dùng database để test payload và worker thật.
        config([
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => false,
            'logging.channels.queue_context_test' => [
                'driver' => 'single',
                'path' => storage_path('logs/queue-context-test.log'),
                'level' => 'debug',
            ],
        ]);

        $this->logPath = storage_path('logs/queue-context-test.log');

        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        // Route chỉ tồn tại trong test để tạo luồng request → dispatch.
        Route::post('/_tests/queue-context', function (Request $request) {
            QueueContextProbeJob::dispatch(
                (int) $request->input('failures_before_success', 0),
            );

            return response()->json([
                'request_id' => $request->attributes->get('request_id'),
            ]);
        })->middleware(AssignRequestId::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->logPath) && is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_request_id_reaches_worker_and_retry_without_logging_private_input(): void
    {
        $requestId = (string) Str::uuid();
        $privateMarker = 'PRIVATE-EVIDENCE-SENTINEL';

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/_tests/queue-context', [
                'failures_before_success' => 1,
                'private_marker' => $privateMarker,
            ])
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('request_id', $requestId);

        $payload = (string) DB::table('jobs')->value('payload');

        // Request body riêng tư không được sao chép vào queue payload.
        $this->assertStringNotContainsString($privateMarker, $payload);

        // Lần đầu lỗi giả lập, lần thứ hai retry thành công.
        $this->workOneJob();
        $this->assertDatabaseCount('jobs', 1);
        $this->workOneJob();
        $this->assertDatabaseCount('jobs', 0);

        $log = (string) file_get_contents($this->logPath);

        // ID được khôi phục vào log context của worker.
        $this->assertStringContainsString('origin_request_id', $log);
        $this->assertStringContainsString($requestId, $log);
        $this->assertStringNotContainsString($privateMarker, $log);

    }

    public function test_schedule_run_id_reaches_worker_logs(): void
    {
        $scheduleRunId = (string) Str::uuid();

        Context::add('schedule_run_id', $scheduleRunId);

        try {
            QueueContextProbeJob::dispatch();
        } finally {
            Context::forget('schedule_run_id');
        }

        $this->workOneJob();

        $log = (string) file_get_contents($this->logPath);

        $this->assertStringContainsString('schedule_run_id', $log);
        $this->assertStringContainsString($scheduleRunId, $log);
    }

    public function test_exhausted_retries_are_recorded_as_failed_job_without_private_input(): void
    {
        $requestId = (string) Str::uuid();
        $privateMarker = 'PRIVATE-EVIDENCE-SENTINEL';

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/_tests/queue-context', [
                'failures_before_success' => 99,
                'private_marker' => $privateMarker,
            ])
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);

        // Job cho phép 2 lần thử: lần đầu retry, lần thứ hai vào failed_jobs.
        $this->workOneJob();
        $this->assertDatabaseCount('failed_jobs', 0);

        $this->workOneJob();
        $this->assertDatabaseCount('failed_jobs', 1);

        $failedPayload = (string) DB::table('failed_jobs')->value('payload');
        $failedException = (string) DB::table('failed_jobs')->value('exception');
        $log = (string) file_get_contents($this->logPath);

        $this->assertStringContainsString($requestId, $failedPayload);
        $this->assertStringNotContainsString($privateMarker, $failedPayload);
        $this->assertStringNotContainsString($privateMarker, $failedException);
        $this->assertStringContainsString('origin_request_id', $log);
        $this->assertStringContainsString($requestId, $log);
        $this->assertStringNotContainsString($privateMarker, $log);

        $failedJobUuid = (string) DB::table('failed_jobs')->value('uuid');

        $this->artisan("queue:retry {$failedJobUuid}")
            ->assertExitCode(0);

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function workOneJob(): void
    {
        $this->artisan('queue:work database --once --tries=2 --sleep=0')
            ->assertExitCode(0);
    }
}
