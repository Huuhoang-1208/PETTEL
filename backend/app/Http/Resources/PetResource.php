<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
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
            'status' => $this->status,
            'photo_media_id' => $this->photo_media_id === null ? null : (string) $this->photo_media_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
