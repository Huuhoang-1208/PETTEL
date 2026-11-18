<?php

namespace App\Http\Resources;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pet */
class PetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
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
            'microchip_number' => $this->microchip_number,
            'is_neutered' => $this->is_neutered,
            'health_flags' => $this->health_flags,
            'behavior_tags' => $this->behavior_tags ?? [],
            'care_support_tags' => $this->care_support_tags ?? [],
            'vaccination_statuses' => $this->vaccination_statuses ?? [],
            'core_vaccines_date' => $this->core_vaccines_date?->toDateString(),
            'rabies_vaccine_date' => $this->rabies_vaccine_date?->toDateString(),
            'deworming_date' => $this->deworming_date?->toDateString(),
            'vaccination_record_media_id' => $this->vaccination_record_media_id === null ? null : (string) $this->vaccination_record_media_id,
            'status' => $this->status,
            'photo_media_id' => $this->photo_media_id === null ? null : (string) $this->photo_media_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
