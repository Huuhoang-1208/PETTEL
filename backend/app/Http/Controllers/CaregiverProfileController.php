<?php

namespace App\Http\Controllers;

use App\Http\Requests\CaregiverProfileRequest;
use App\Http\Resources\CaregiverProfileResource;
use App\Models\CaregiverProfile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CaregiverProfileController extends Controller
{
    public function store(CaregiverProfileRequest $request): JsonResponse
    {
        try {
            $profile = $request->user()->caregiverProfile()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            $this->reject(409, 'INVALID_STATE', 'A caregiver profile already exists.');
        }

        return CaregiverProfileResource::make($profile->refresh())
            ->response()->setStatusCode(201);
    }

    public function show(Request $request): CaregiverProfileResource
    {
        return CaregiverProfileResource::make($this->ownedProfile($request));
    }

    public function update(CaregiverProfileRequest $request): CaregiverProfileResource
    {
        $profile = $this->ownedProfile($request);
        $profile->update($request->validated());

        return CaregiverProfileResource::make($profile->refresh());
    }

    private function ownedProfile(Request $request): CaregiverProfile
    {
        $profile = $request->user()->caregiverProfile()->first();

        if ($profile === null) {
            $this->reject(404, 'NOT_FOUND', 'Caregiver profile not found.');
        }

        return $profile;
    }

    private function reject(int $status, string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
