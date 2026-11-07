<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'sender_id',
        'body',
        'media_object_id',
        'visibility',
        'created_at',
        'redacted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'redacted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<MediaObject, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaObject::class, 'media_object_id');
    }
}
