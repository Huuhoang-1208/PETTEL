<?php

namespace App\Http\Requests;

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
            'name', 'species', 'birth_date', 'age_note', 'sex', 'size',
            'diet_notes', 'vaccination_note', 'temperament', 'special_needs',
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

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'species' => [$required, 'string', Rule::in(['DOG', 'CAT', 'OTHER'])],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'age_note' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sex' => ['sometimes', 'nullable', Rule::in(['MALE', 'FEMALE', 'UNKNOWN'])],
            'size' => ['sometimes', 'nullable', Rule::in(['SMALL', 'MEDIUM', 'LARGE'])],
            'diet_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'vaccination_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'temperament' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'special_needs' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'photo_media_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('media_objects', 'id')->where(fn (Builder $query) => $query
                    ->where('owner_user_id', $this->user()->id)
                    ->where('state', 'READY')),
            ],
        ];
    }
}
