<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\ExpireBooking;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBookingExpiries extends Command
{
    protected $signature = 'bookings:process-expiries {--batch=100}';

    protected $description = 'Expire REQUESTED and AWAITING_PAYMENT bookings without losing payment history.';

    public function handle(ExpireBooking $expireBooking): int
    {
        $now = CarbonImmutable::now('UTC');
        $batch = max(1, min((int) $this->option('batch'), 100));
        $ids = Booking::query()
            ->where(function ($query) use ($now): void {
                $query->where(function ($requested) use ($now): void {
                    $requested->where('status', 'REQUESTED')
                        ->where(function ($deadline) use ($now): void {
                            $deadline->where('created_at', '<=', $now->subHours(24))
                                ->orWhere('start_at', '<=', $now);
                        });
                })->orWhere(function ($payment) use ($now): void {
                    $payment->where('status', 'AWAITING_PAYMENT')
                        ->whereNotNull('payment_due_at')
                        ->where('payment_due_at', '<=', $now);
                });
            })
            ->whereNotExists(function ($operations): void {
                $operations->selectRaw('1')
                    ->from('booking_cancellation_operations as cancellation_operations')
                    ->whereColumn('cancellation_operations.booking_id', 'bookings.id')
                    ->whereIn('cancellation_operations.status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW']);
            })
            ->orderBy('id')
            ->limit($batch)
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            try {
                if ($expireBooking->handle((string) $id)) {
                    $expired++;
                }
            } catch (Throwable $exception) {
                Log::warning('booking_expiry_candidate_failed', [
                    'booking_id' => (string) $id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $stats = ['selected' => $ids->count(), 'accepted' => $expired, 'skipped_or_failed' => $ids->count() - $expired];
        Log::info('booking_expiries_processed', $stats);
        $this->components->info('Processed '.$stats['selected'].' candidates; '.$stats['accepted'].' transitioned or queued.');

        return self::SUCCESS;
    }
}
