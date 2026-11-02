<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Normalizer;

class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ($this->only(['title', 'description', 'service_type', 'area_code', 'price_unit']) as $field => $value) {
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
        $nested = $this->isMethod('POST') || array_key_exists('acceptance_rules_json', $this->all())
            ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:120'],
            'description' => [$presence, 'string', 'max:2000'],
            'service_type' => [$presence, Rule::in(['DAYCARE', 'BOARDING'])],
            'area_code' => [
                $presence,
                Rule::in([
                    'DN_CENTER',
                    'DN_NORTH',
                    'DN_SOUTH',
                    'DN_WEST',
                    'DN_EAST',
                ]),
            ],
            'price_vnd' => [$presence, 'integer:strict', 'min:1', 'max:'.PHP_INT_MAX],
            'price_unit' => [$presence, Rule::in(['HOUR', 'NIGHT'])],
            'acceptance_rules_json' => [$presence, 'array:schema_version,species,sizes'],
            'acceptance_rules_json.schema_version' => [$nested, 'integer:strict', Rule::in([1])],
            'acceptance_rules_json.species' => [$nested, 'array', 'list', 'min:1'],
            'acceptance_rules_json.species.*' => ['string', Rule::in(['DOG', 'CAT', 'OTHER'])],
            'acceptance_rules_json.sizes' => [$nested, 'array', 'list', 'min:1'],
            'acceptance_rules_json.sizes.*' => ['string', Rule::in(['SMALL', 'MEDIUM', 'LARGE'])],
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
            'listing_media' => ['missing'],
            'availability_slots' => ['missing'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid listing data.',
                'details' => $validator->errors()->messages(),
                'request_id' => (string) Str::uuid(),
            ],
        ], 422));
    }
}
