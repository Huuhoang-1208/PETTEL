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
        'birth_date',
        'age_note',
        'sex',
        'size',
        'diet_notes',
        'vaccination_note',
        'temperament',
        'special_needs',
        'photo_media_id',
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
            'birth_date' => $this->birth_date?->toDateString(),
            'age_note' => $this->age_note,
            'sex' => $this->sex,
            'size' => $this->size,
            'diet_notes' => $this->diet_notes,
            'vaccination_note' => $this->vaccination_note,
            'temperament' => $this->temperament,
            'special_needs' => $this->special_needs,
        ];
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }
}
