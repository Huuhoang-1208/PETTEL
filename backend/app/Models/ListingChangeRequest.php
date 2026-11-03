<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property array{schema_version: int, data: array<string, mixed>} $proposed_data_json */
class ListingChangeRequest extends Model
{
    protected $table = 'listing_change_requests';

    protected $fillable = [
        'listing_id', 'base_listing_version', 'proposed_data_json', 'status',
        'requested_by_user_id', 'reviewed_by_user_id', 'decision_reason',
        'requested_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['proposed_data_json' => 'array', 'requested_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime'];
    }
}
