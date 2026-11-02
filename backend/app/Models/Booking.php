<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 * @property CarbonImmutable|null $payment_due_at
 */
class Booking extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'owner_id',
        'pet_id',
        'listing_id',
        'caregiver_user_id',
        'start_at',
        'end_at',
        'status',
        'price_snapshot_vnd',
        'price_unit',
        'price_quantity',
        'pricing_version',
        'cancellation_policy_version',
        'pet_snapshot_json',
        'listing_snapshot_json',
        'policy_snapshot_json',
        'instructions_snapshot',
        'payment_due_at',
        'current_payment_attempt_id',
        'request_idempotency_key',
        'request_payload_hash',
        'version',
    ];

    protected $hidden = ['request_idempotency_key', 'request_payload_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pet_snapshot_json' => 'array',
            'listing_snapshot_json' => 'array',
            'policy_snapshot_json' => 'array',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'payment_due_at' => 'immutable_datetime',
            'privacy_redacted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'price_snapshot_vnd' => 'integer',
            'price_quantity' => 'integer',
            'pricing_version' => 'integer',
            'cancellation_policy_version' => 'integer',
            'version' => 'integer',
        ];
    }
}
