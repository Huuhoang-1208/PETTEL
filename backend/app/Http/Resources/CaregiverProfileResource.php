<?php

namespace App\Http\Resources;

use App\Models\CaregiverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CaregiverProfile */
class CaregiverProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'area_code' => $this->area_code,
            // This resource is only returned from the authenticated, owner-scoped caregiver API.
            'application_data_json' => $this->application_data_json ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
