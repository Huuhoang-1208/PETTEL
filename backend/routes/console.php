<?php

use App\Jobs\InfrastructureHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (app()->environment('local')) {
    Schedule::job(new InfrastructureHeartbeat)->everyMinute();
}

Schedule::command('bookings:process-expiries --batch=100')->everyMinute();
Schedule::command('bookings:process-cancellations --batch=100')->everyMinute();
Schedule::command('care-updates:redact-expired --batch=100')->daily();
Schedule::command('bookings:process-releases --batch=100')->everyMinute();
