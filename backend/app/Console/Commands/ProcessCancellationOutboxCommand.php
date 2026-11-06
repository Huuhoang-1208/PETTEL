<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ProcessCancellationOutbox;
use Illuminate\Console\Command;

class ProcessCancellationOutboxCommand extends Command
{
    protected $signature = 'bookings:process-cancellations {--batch=100}';

    protected $description = 'Process due cancellation outbox operations.';

    public function handle(ProcessCancellationOutbox $processor): int
    {
        $counts = $processor->handle((int) $this->option('batch'));
        $this->components->info('Processed '.$counts['processed'].' cancellation operations.');

        return self::SUCCESS;
    }
}
