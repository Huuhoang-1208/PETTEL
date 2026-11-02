<?php

namespace App\Http\Requests;

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
            'id' => ['missing'],
            'user_id' => ['missing'],
            'caregiver_profile_id' => ['missing'],
            'status' => ['missing'],
            'capacity' => ['missing'],
            'version' => ['missing'],
            'published_at' => ['missing'],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
            'deleted_at' => ['missing'],
        ];
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
