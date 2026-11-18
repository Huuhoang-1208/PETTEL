<?php

namespace App\Http\Requests;

use App\Models\MediaObject;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Normalizer;

class PetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ($this->only([
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
            'breed',
            'weight_kg',
            'microchip_number',
            'core_vaccines_date',
            'rabies_vaccine_date',
            'deworming_date',
        ]) as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
                $value = $normalized === false ? $value : $normalized;
                $values[$key] = $value === '' ? null : $value;
            }
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'species' => [$required, 'string', Rule::in(['DOG', 'CAT', 'OTHER'])],
            'species_detail' => ['required_if:species,OTHER', 'nullable', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'age_note' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sex' => ['sometimes', 'nullable', 'string', Rule::in(['MALE', 'FEMALE', 'UNKNOWN'])],
            'size' => ['sometimes', 'nullable', 'string', Rule::in(['SMALL', 'MEDIUM', 'LARGE'])],
            'diet_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'food_allergies' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'foods_to_avoid' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'vaccination_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'temperament' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'special_needs' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'breed' => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:200'],
            'microchip_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_neutered' => ['sometimes', 'boolean'],
            'health_flags' => ['sometimes', 'nullable', 'array:core_vaccines,rabies_vaccine,deworming'],
            'health_flags.core_vaccines' => ['sometimes', 'boolean'],
            'health_flags.rabies_vaccine' => ['sometimes', 'boolean'],
            'health_flags.deworming' => ['sometimes', 'boolean'],
            'behavior_tags' => ['sometimes', 'array', 'max:12'],
            'behavior_tags.*' => ['string', Rule::in([
                'SHY', 'FEARS_STRANGERS', 'NEEDS_WARM_UP', 'NOT_SOCIAL_WITH_PETS',
                'SEPARATION_ANXIETY', 'HIGH_ENERGY', 'SENSITIVE_TO_NOISE',
            ])],
            'care_support_tags' => ['sometimes', 'array', 'max:12'],
            'care_support_tags.*' => ['string', Rule::in([
                'ORAL_MEDICATION', 'EYE_DROPS', 'SPECIAL_DIET', 'POST_SURGERY',
                'MOBILITY_SUPPORT', 'OTHER_SPECIAL_CARE',
            ])],
            'vaccination_statuses' => ['sometimes', 'array:core_vaccines,rabies_vaccine,deworming'],
            'vaccination_statuses.core_vaccines' => ['sometimes', Rule::in(['DONE', 'NOT_DONE', 'UNKNOWN'])],
            'vaccination_statuses.rabies_vaccine' => ['sometimes', Rule::in(['DONE', 'NOT_DONE', 'UNKNOWN'])],
            'vaccination_statuses.deworming' => ['sometimes', Rule::in(['DONE', 'NOT_DONE', 'UNKNOWN'])],
            'core_vaccines_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rabies_vaccine_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'deworming_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'photo_media_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('media_objects', 'id')->where(fn (Builder $query) => $query
                    ->where('owner_user_id', $this->user()->id)
                    ->where('state', 'READY')
                    ->whereNotIn('id', MediaObject::messageAttachmentIds())),
            ],
            'vaccination_record_media_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('media_objects', 'id')->where(fn (Builder $query) => $query
                    ->where('owner_user_id', $this->user()->id)
                    ->where('state', 'READY')
                    ->whereNotIn('id', MediaObject::messageAttachmentIds())),
            ],
        ];
    }
}
