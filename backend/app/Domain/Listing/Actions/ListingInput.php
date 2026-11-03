<?php

namespace App\Domain\Listing\Actions;

use Illuminate\Http\Request;

class ListingInput
{
    /**
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function body(Request $request, array $allowed, array $rules): array
    {
        return $this->validate($request->all(), $allowed, $rules);
    }

    /**
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function query(Request $request, array $allowed, array $rules): array
    {
        return $this->validate($request->query(), $allowed, $rules);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $allowed
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validate(array $input, array $allowed, array $rules): array
    {
        $unknown = array_values(array_map('strval', array_diff(array_keys($input), $allowed)));
        if ($unknown !== []) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Unknown request fields.', array_fill_keys($unknown, ['Unknown field.']));
        }

        $validator = validator($input, $rules);
        if ($validator->fails()) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Invalid request data.', $validator->errors()->toArray());
        }

        return $validator->validated();
    }
}
