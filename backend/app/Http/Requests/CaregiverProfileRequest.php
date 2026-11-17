<?php

namespace App\Http\Requests;

use App\Models\MediaObject;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Normalizer;

class CaregiverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ($this->only(['type', 'display_name', 'bio', 'area_code']) as $field => $value) {
            if (is_string($value)) {
                $normalized = Normalizer::normalize(trim($value), Normalizer::FORM_C);
                $value = $normalized === false ? trim($value) : $normalized;
                $values[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($values);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'type' => [$presence, 'string', Rule::in(['INDIVIDUAL', 'FACILITY'])],
            'display_name' => [$presence, 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'area_code' => [
                $presence,
                'string',
                Rule::in([
                    'DN_CENTER',
                    'DN_NORTH',
                    'DN_SOUTH',
                    'DN_WEST',
                    'DN_EAST',
                ]),
            ],
            'application_data_json' => ['sometimes', 'array:private_address,experience_species,care_skills,capacity,housing_type,space_type,amenities,space_media_ids,avatar_media_id,contact_phone,contact_email,safety_plan,kyc_front_media_id,kyc_back_media_id,certificate_media_id,agreements,experience_years,care_principles,pet_requirements'],
            'application_data_json.private_address' => ['sometimes', 'nullable', 'string', 'max:300'],
            'application_data_json.experience_species' => ['sometimes', 'array', 'list', 'max:6'],
            'application_data_json.experience_species.*' => ['string', Rule::in(['DOG_SMALL', 'DOG_MEDIUM', 'DOG_LARGE', 'CAT_SHORT_HAIR', 'CAT_LONG_HAIR', 'SMALL_PETS'])],
            'application_data_json.care_skills' => ['sometimes', 'array', 'list', 'max:4'],
            'application_data_json.care_skills.*' => ['string', Rule::in(['MEDICATION', 'SENIOR_OR_SHY', 'SPECIAL_DIET', 'POST_SURGERY'])],
            'application_data_json.capacity' => ['sometimes', 'string', Rule::in(['1', '2', '4'])],
            'application_data_json.housing_type' => ['sometimes', 'string', Rule::in(['APARTMENT', 'HOUSE', 'VILLA'])],
            'application_data_json.space_type' => ['sometimes', 'string', Rule::in(['INDOOR', 'INDOOR_YARD'])],
            'application_data_json.amenities' => ['sometimes', 'array', 'list', 'max:6'],
            'application_data_json.amenities.*' => ['string', Rule::in(['BALCONY_NET', 'HEPA_FILTER', 'CAGE_FREE', 'AIR_CONDITIONING', 'SEPARATE_FOOD_STORAGE', 'CAMERA'])],
            'application_data_json.space_media_ids' => ['sometimes', 'array', 'list', 'max:8'],
            'application_data_json.space_media_ids.*' => ['required', 'integer', 'min:1'],
            'application_data_json.avatar_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'application_data_json.contact_phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'application_data_json.contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'application_data_json.safety_plan' => ['sometimes', 'nullable', 'string', 'max:1500'],
            'application_data_json.kyc_front_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'application_data_json.kyc_back_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'application_data_json.certificate_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'application_data_json.agreements' => ['sometimes', 'array:partner_terms,care_commitment'],
            'application_data_json.agreements.partner_terms' => ['sometimes', 'boolean'],
            'application_data_json.agreements.care_commitment' => ['sometimes', 'boolean'],
            'application_data_json.experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'application_data_json.care_principles' => ['sometimes', 'array', 'list', 'max:3'],
            'application_data_json.care_principles.*' => ['required', 'array:title,description'],
            'application_data_json.care_principles.*.title' => ['required', 'string', 'max:80'],
            'application_data_json.care_principles.*.description' => ['required', 'string', 'max:300'],
            'application_data_json.pet_requirements' => ['sometimes', 'array', 'list', 'max:5'],
            'application_data_json.pet_requirements.*' => ['string', Rule::in([
                'REQUIRE_VACCINATION',
                'ACCEPTS_MEDICATION',
                'ACCEPTS_SENIOR_OR_SHY',
                'ACCEPTS_SPECIAL_DIET',
                'ACCEPTS_POST_SURGERY',
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $application = $this->input('application_data_json', []);
            if (is_array($application)) {
                $mediaIds = array_filter([
                    $application['avatar_media_id'] ?? null,
                    $application['kyc_front_media_id'] ?? null,
                    $application['kyc_back_media_id'] ?? null,
                    $application['certificate_media_id'] ?? null,
                    ...((array) ($application['space_media_ids'] ?? [])),
                ]);
                if ($mediaIds !== []) {
                    $ownedIds = MediaObject::query()
                        ->whereIn('id', $mediaIds)
                        ->where('owner_user_id', $this->user()?->id)
                        ->where('state', 'READY')
                        ->where('visibility', 'PRIVATE')
                        ->where('mime_type', 'like', 'image/%')
                        ->pluck('id')->map(fn ($id): int => (int) $id)->all();
                    foreach ($mediaIds as $id) {
                        if (! in_array((int) $id, $ownedIds, true)) {
                            $validator->errors()->add('application_data_json', 'Uploaded images must belong to your account.');
                            break;
                        }
                    }
                }
            }
            foreach ([
                'id',
                'user_id',
                'caregiver_profile_id',
                'status',
                'capacity',
                'version',
                'published_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ] as $field) {
                if (array_key_exists($field, $this->getInputSource()->all())) {
                    $validator->errors()->add($field, "The {$field} field must not be present.");
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid caregiver profile data.',
                'details' => $validator->errors()->messages(),
                'request_id' => (string) Str::uuid(),
            ],
        ], 422));
    }
}
