<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property int $id
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable $end_at
 */
class AvailabilitySlot extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'start_at',
        'end_at',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array{start_at: 'immutable_datetime', end_at: 'immutable_datetime'} */
    protected function casts(): array
    {
        return [
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
        ];
    }
}
