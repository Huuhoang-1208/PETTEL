<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaregiverProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'display_name',
        'bio',
        'demo_profile_json',
        'area_code',
        'application_data_json',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return array{demo_profile_json: 'array'} */
    protected function casts(): array
    {
        return [
            'demo_profile_json' => 'array',
            'application_data_json' => 'array',
        ];
    }
}
