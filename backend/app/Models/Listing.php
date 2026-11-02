<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'service_type',
        'area_code',
        'price_vnd',
        'price_unit',
        'acceptance_rules_json',
    ];

    protected $attributes = [
        'status' => 'DRAFT',
        'capacity' => 1,
        'version' => 1,
    ];

    /** @return BelongsTo<CaregiverProfile, $this> */
    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }

    /** @return BelongsToMany<MediaObject, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaObject::class, 'listing_media')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return HasMany<AvailabilitySlot, $this> */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class)
            ->orderBy('start_at')
            ->orderBy('id');
    }

    /** @return array{price_vnd: 'integer', capacity: 'integer', version: 'integer', acceptance_rules_json: 'array', published_at: 'datetime'} */
    protected function casts(): array
    {
        return [
            'price_vnd' => 'integer',
            'capacity' => 'integer',
            'version' => 'integer',
            'acceptance_rules_json' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
