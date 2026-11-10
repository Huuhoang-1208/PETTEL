<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingCompletionReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBookingCompletionFollowups
{
    /**
     * @return array{reminded: int, queued: int, skipped: int, failed: int}
     */
    public function handle(int $batchSize = 100): array
    {
        $now = CarbonImmutable::now('UTC');
        $reminderCutoff = $now->subHours(24);
        $adminQueueCutoff = $now->subHours(72);

        $latestEvents = DB::table('booking_status_events')
            ->select('booking_id')
            ->selectRaw('MAX(id) as event_id')
            ->groupBy('booking_id');

        $bookingIds = DB::table('bookings')
            ->joinSub($latestEvents, 'latest_events', function ($join): void {
                $join->on('latest_events.booking_id', '=', 'bookings.id');
            })
            ->join('booking_status_events as latest_event', 'latest_event.id', '=', 'latest_events.event_id')
            ->leftJoin(
                'booking_completion_followups as followups',
                'followups.booking_id',
                '=',
                'bookings.id',
            )
            ->where('bookings.status', 'AWAITING_CONFIRMATION')
            ->where('latest_event.to_status', 'AWAITING_CONFIRMATION')
            ->where('latest_event.created_at', '<=', $reminderCutoff->format('Y-m-d H:i:s.u'))
            ->where(function ($query) use ($adminQueueCutoff): void {
                $query->whereNull('followups.id')
                    ->orWhereNull('followups.reminder_sent_at')
                    ->orWhere(function ($query) use ($adminQueueCutoff): void {
                        $query->where(
                            'latest_event.created_at',
                            '<=',
                            $adminQueueCutoff->format('Y-m-d H:i:s.u'),
                        )->whereNull('followups.admin_queued_at');
                    });
            })
            ->orderBy('latest_event.created_at')
            ->limit(max(1, min($batchSize, 500)))
            ->pluck('bookings.id');

        $counts = [
            'reminded' => 0,
            'queued' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($bookingIds as $bookingId) {
            try {
                $result = $this->processOne((int) $bookingId, $now);

                $counts['reminded'] += $result['reminded'] ? 1 : 0;
                $counts['queued'] += $result['queued'] ? 1 : 0;
                $counts['skipped'] += $result['skipped'] ? 1 : 0;
            } catch (Throwable $exception) {
                // Avoid logging report, pet, payment, or notification contents.
                Log::warning('booking_completion_followup_failed', [
                    'booking_id' => (string) $bookingId,
                    'exception' => $exception::class,
                ]);

                $counts['failed']++;
            }
        }

        Log::info('booking_completion_followups_processed', $counts);

        return $counts;
    }

    /**
     * @return array{reminded: bool, queued: bool, skipped: bool}
     */
    private function processOne(int $bookingId, CarbonImmutable $now): array
    {
        return DB::transaction(function () use ($bookingId, $now): array {
            // Booking is locked before follow-up metadata, matching booking transition flows.
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if ($booking === null || $booking->status !== 'AWAITING_CONFIRMATION') {
                $this->resolveFollowup($bookingId, $now);

                return ['reminded' => false, 'queued' => false, 'skipped' => true];
            }

            $latestEvent = DB::table('booking_status_events')
                ->where('booking_id', $booking->id)
                ->orderByDesc('id')
                ->first(['to_status', 'created_at']);

            if ($latestEvent === null || $latestEvent->to_status !== 'AWAITING_CONFIRMATION') {
                $this->resolveFollowup($bookingId, $now);

                return ['reminded' => false, 'queued' => false, 'skipped' => true];
            }

            $awaitingSince = CarbonImmutable::parse($latestEvent->created_at, 'UTC');

            if ($awaitingSince->addHours(24)->isAfter($now)) {
                return ['reminded' => false, 'queued' => false, 'skipped' => true];
            }

            DB::table('booking_completion_followups')->insertOrIgnore([
                'booking_id' => $booking->id,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
                'updated_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            $followup = DB::table('booking_completion_followups')
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($followup === null) {
                throw new \RuntimeException('Could not create booking completion follow-up.');
            }

            $reminded = false;
            $queued = false;
            $updates = [
                'updated_at' => $now->format('Y-m-d H:i:s.u'),
            ];

            if ($followup->reminder_sent_at === null) {
                $owner = User::query()->find($booking->owner_id);

                if ($owner !== null) {
                    // Database notification and follow-up timestamp share this transaction.
                    $owner->notify(new BookingCompletionReminder((string) $booking->id));
                    $updates['reminder_sent_at'] = $now->format('Y-m-d H:i:s.u');
                    $reminded = true;
                }
            }

            if (
                $awaitingSince->addHours(72)->lessThanOrEqualTo($now)
                && $followup->admin_queued_at === null
            ) {
                $updates['admin_queued_at'] = $now->format('Y-m-d H:i:s.u');
                $queued = true;
            }

            if ($reminded || $queued) {
                DB::table('booking_completion_followups')
                    ->where('booking_id', $booking->id)
                    ->update($updates);
            }

            return ['reminded' => $reminded, 'queued' => $queued, 'skipped' => false];
        }, 3);
    }

    private function resolveFollowup(int $bookingId, CarbonImmutable $now): void
    {
        DB::table('booking_completion_followups')
            ->where('booking_id', $bookingId)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => $now->format('Y-m-d H:i:s.u'),
                'updated_at' => $now->format('Y-m-d H:i:s.u'),
            ]);
    }
}
