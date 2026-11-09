<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ProcessBookingReleaseOutbox;
use Illuminate\Console\Command;

class ProcessBookingReleaseOutboxCommand extends Command
{
    protected $signature = 'bookings:process-releases {--batch=100}';

    protected $description = 'Process due booking release operations.';

    public function handle(ProcessBookingReleaseOutbox $processor): int
    {
        $counts = $processor->handle((int) $this->option('batch'));
        $this->components->info('Processed '.$counts['processed'].' release operations.');

        return self::SUCCESS;
    }
}
