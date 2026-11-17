<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pet extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'species',
        'species_detail',
        'birth_date',
        'age_note',
        'sex',
        'size',
        'diet_notes',
        'food_allergies',
        'foods_to_avoid',
        'vaccination_note',
        'temperament',
        'special_needs',
        'photo_media_id',
        'breed',
        'weight_kg',
        'microchip_number',
        'is_neutered',
        'health_flags',
        'behavior_tags',
        'care_support_tags',
        'vaccination_statuses',
        'core_vaccines_date',
        'rabies_vaccine_date',
        'deworming_date',
        'vaccination_record_media_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, mixed> */
    public function snapshotForBooking(): array
    {
        return [
            'schema_version' => 1,
            'pet_id' => (string) $this->getKey(),
            'name' => $this->name,
            'species' => $this->species,
            'species_detail' => $this->species_detail,
            'breed' => $this->breed,
            'birth_date' => $this->birth_date?->toDateString(),
            'age_note' => $this->age_note,
            'sex' => $this->sex,
            'size' => $this->size,
            'diet_notes' => $this->diet_notes,
            'food_allergies' => $this->food_allergies,
            'foods_to_avoid' => $this->foods_to_avoid,
            'vaccination_note' => $this->vaccination_note,
            'temperament' => $this->temperament,
            'special_needs' => $this->special_needs,
            'weight_kg' => $this->weight_kg,
            'is_neutered' => $this->is_neutered,
            'health_flags' => $this->health_flags,
            'behavior_tags' => $this->behavior_tags,
            'care_support_tags' => $this->care_support_tags,
            'vaccination_statuses' => $this->vaccination_statuses,
            'core_vaccines_date' => $this->core_vaccines_date?->toDateString(),
            'rabies_vaccine_date' => $this->rabies_vaccine_date?->toDateString(),
            'deworming_date' => $this->deworming_date?->toDateString(),
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'weight_kg' => 'decimal:2',
            'is_neutered' => 'boolean',
            'health_flags' => 'array',
            'behavior_tags' => 'array',
            'care_support_tags' => 'array',
            'vaccination_statuses' => 'array',
            'core_vaccines_date' => 'date',
            'rabies_vaccine_date' => 'date',
            'deworming_date' => 'date',
        ];
    }
}
