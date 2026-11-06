<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $next_retry_at
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $completed_at
 */
class BookingCancellationOperation extends Model
{
    protected $table = 'booking_cancellation_operations';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'next_retry_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
