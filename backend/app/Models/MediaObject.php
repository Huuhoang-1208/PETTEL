<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read ListingMedia $pivot */
class MediaObject extends Model
{
    protected $fillable = [
        'object_key',
        'mime_type',
        'byte_size',
        'sha256',
    ];

    protected $attributes = [
        'visibility' => 'PRIVATE',
        'state' => 'READY',
    ];

    protected $hidden = [
        'owner_user_id',
        'object_key',
        'sha256',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<Listing, $this> */
    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_media')
            ->withPivot('sort_order');
    }

    /** @return HasMany<CareUpdate, $this> */
    public function careUpdates(): HasMany
    {
        return $this->hasMany(CareUpdate::class, 'media_object_id');
    }

    /** @return array{byte_size: 'integer'} */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
        ];
    }
}
