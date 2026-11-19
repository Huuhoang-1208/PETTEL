<?php

namespace App\Models;

use Database\Factories\CaregiverProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $application_data_json
 * @property array<string, mixed>|null $demo_profile_json
 */
class CaregiverProfile extends Model
{
    /** @use HasFactory<CaregiverProfileFactory> */
    use HasFactory;

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
