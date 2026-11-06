<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Normalizer;

class RetryCancellationOperationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        if (is_string($reason)) {
            $normalized = Normalizer::normalize($reason, Normalizer::FORM_C);
            $reason = trim($normalized === false ? $reason : $normalized);
        }
        $this->merge(['reason' => $reason]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:500']];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => $validator->errors(),
                'request_id' => (string) Str::uuid(),
            ],
        ], 422));
    }
}
