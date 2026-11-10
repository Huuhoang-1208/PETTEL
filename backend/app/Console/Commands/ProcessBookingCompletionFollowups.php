<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ProcessBookingCompletionFollowups as Processor;
use Illuminate\Console\Command;

class ProcessBookingCompletionFollowups extends Command
{
    protected $signature = 'bookings:process-completion-followups {--batch=100}';

    protected $description = 'Send completion reminders and queue overdue bookings for admin review.';

    public function handle(Processor $processor): int
    {
        $counts = $processor->handle((int) $this->option('batch'));

        $this->components->info(
            'Reminded '.$counts['reminded']
            .' owners and queued '.$counts['queued'].' bookings for admin review.'
        );

        return self::SUCCESS;
    }
}
