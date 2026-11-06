<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Normalizer;

class CancelBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['Idempotency-Key' => $this->header('Idempotency-Key')]);

        $note = $this->input('reason_note');
        if (is_string($note)) {
            $normalized = Normalizer::normalize($note, Normalizer::FORM_C);
            $note = trim($normalized === false ? $note : $normalized);
        }

        $this->merge(['reason_note' => $note === '' ? null : $note]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'Idempotency-Key' => ['required', 'string', 'regex:/\A[a-z0-9._:-]{1,128}\z/'],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ];
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
