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
        $user = $this->user;
        $application = is_array($this->application_data_json) ? $this->application_data_json : [];
        $rawContactEmail = $application['contact_email'] ?? null;
        $contactEmail = is_string($rawContactEmail)
            ? strtolower(trim($rawContactEmail))
            : '';
        $accountEmail = strtolower((string) $user?->email);

        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'area_code' => $this->area_code,
            // This resource is only returned from the authenticated, owner-scoped caregiver API.
            'application_data_json' => $application,
            'contact_verification' => [
                'email_verified' => $user !== null && $user->email_verified_at !== null
                    && $contactEmail !== ''
                    && $contactEmail === $accountEmail,
                'email_verification_available' => false,
                // Phone OTP verification is not configured in this project yet.
                'phone_verified' => false,
                'phone_verification_available' => false,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
