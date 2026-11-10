<?php

use App\Jobs\InfrastructureHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$runScheduledCommand = static function (string $command, array $parameters = []): void {
    $scheduleRunId = (string) Str::uuid();

    Context::add('schedule_run_id', $scheduleRunId);
    Log::withContext(['schedule_run_id' => $scheduleRunId]);

    Log::info('scheduled_command.started', ['command' => $command]);

    try {
        $exitCode = Artisan::call($command, $parameters);

        Log::log(
            $exitCode === 0 ? 'info' : 'error',
            'scheduled_command.finished',
            ['command' => $command, 'exit_code' => $exitCode],
        );

        if ($exitCode !== 0) {
            throw new RuntimeException("Scheduled command failed: {$command}");
        }
    } finally {
        Context::forget('schedule_run_id');
        Log::withoutContext();
    }
};

Schedule::call(fn () => $runScheduledCommand(
    'bookings:process-expiries',
    ['--batch' => 100],
))->name('bookings:process-expiries')->everyMinute();

Schedule::call(fn () => $runScheduledCommand(
    'bookings:process-cancellations',
    ['--batch' => 100],
))->name('bookings:process-cancellations')->everyMinute();

Schedule::call(fn () => $runScheduledCommand(
    'care-updates:redact-expired',
    ['--batch' => 100],
))->name('care-updates:redact-expired')->daily();

Schedule::call(fn () => $runScheduledCommand(
    'bookings:process-releases',
    ['--batch' => 100],
))->name('bookings:process-releases')->everyMinute();

Schedule::call(fn () => $runScheduledCommand(
    'bookings:process-completion-followups',
    ['--batch' => 100],
))->name('bookings:process-completion-followups')->everyMinute();

if (app()->environment('local')) {
    Schedule::job(new InfrastructureHeartbeat)
        ->everyMinute()
        ->before(function (): void {
            Context::add('schedule_run_id', (string) Str::uuid());
        })
        ->after(function (): void {
            Context::forget('schedule_run_id');
        });
}
