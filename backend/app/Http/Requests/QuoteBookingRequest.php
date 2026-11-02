<?php

namespace App\Http\Requests;

class QuoteBookingRequest extends StoreBookingRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['Idempotency-Key'], $rules['instructions']);

        return $rules;
    }
}
