<?php

namespace App\Http\Requests;

use App\Domain\Booking\Actions\CalculateBookingPrice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Normalizer;

class SearchListingRequest extends FormRequest
{
    /**
     * @var array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     service_types: list<string>
     * }|null
     */
    private ?array $timeRange = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->query('q');

        if (is_string($keyword)) {
            $normalized = Normalizer::normalize(trim($keyword), Normalizer::FORM_C);
            $this->query->set('q', $normalized === false ? trim($keyword) : $normalized);
        }
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->query();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $timestamp = [
            'filled',
            'string',
            'date',
            'regex:/\A\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-3]):[0-5]\d|[+-]14:00)\z/',
        ];

        $integerQuery = [
            'bail',
            'sometimes',
            'required',
            'string',
            'regex:/\A[0-9]+\z/',
            'integer',
        ];

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'service_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['DAYCARE', 'BOARDING']),
            ],
            'area_code' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['DN_CENTER', 'DN_NORTH', 'DN_SOUTH', 'DN_WEST', 'DN_EAST']),
            ],
            'species' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['DOG', 'CAT', 'OTHER']),
            ],
            'size' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['SMALL', 'MEDIUM', 'LARGE']),
            ],
            'page' => [...$integerQuery, 'min:1'],
            'per_page' => [...$integerQuery, 'between:1,50'],
            'min_price_vnd' => [...$integerQuery, 'min:0'],
            'max_price_vnd' => [...$integerQuery, 'min:0'],
            'start_at' => ['bail', 'required_with:end_at', ...$timestamp],
            'end_at' => ['bail', 'required_with:start_at', ...$timestamp],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'This query parameter is not supported.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $page = (int) $this->query('page', 1);
            $perPage = (int) $this->query('per_page', 20);

            if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
                $validator->errors()->add('page', 'Page exceeds the supported range.');
            }

            $hasMinimum = $this->query->has('min_price_vnd');
            $hasMaximum = $this->query->has('max_price_vnd');
            $hasDates = $this->query->has('start_at') && $this->query->has('end_at');

            if (($hasMinimum || $hasMaximum) && ! $hasDates) {
                foreach (['min_price_vnd', 'max_price_vnd'] as $field) {
                    if ($this->query->has($field)) {
                        $validator->errors()->add(
                            $field,
                            'Choose both start_at and end_at to filter total price.',
                        );
                    }
                }
            }

            if (
                $hasMinimum
                && $hasMaximum
                && (int) $this->query('min_price_vnd') > (int) $this->query('max_price_vnd')
            ) {
                $validator->errors()->add(
                    'max_price_vnd',
                    'Maximum price must be greater than or equal to minimum price.',
                );
            }

            if ($validator->errors()->isNotEmpty() || ! $hasDates) {
                return;
            }

            $start = CarbonImmutable::parse((string) $this->query('start_at'))->utc();
            $end = CarbonImmutable::parse((string) $this->query('end_at'))->utc();

            if ($start->lessThanOrEqualTo(CarbonImmutable::now())) {
                $validator->errors()->add('start_at', 'Choose a future start time.');

                return;
            }

            if ($end->lessThanOrEqualTo($start)) {
                $validator->errors()->add('end_at', 'End time must be after start time.');

                return;
            }

            $serviceTypes = [];

            foreach (['DAYCARE' => 'HOUR', 'BOARDING' => 'NIGHT'] as $type => $unit) {
                if ($this->filled('service_type') && $this->query('service_type') !== $type) {
                    continue;
                }

                try {
                    app(CalculateBookingPrice::class)->calculate(
                        $type,
                        $unit,
                        1,
                        $start,
                        $end,
                    );

                    $serviceTypes[] = $type;
                } catch (HttpResponseException $exception) {
                    if ($exception->getResponse()->getStatusCode() !== 422) {
                        throw $exception;
                    }
                }
            }

            if ($serviceTypes === []) {
                $validator->errors()->add(
                    'end_at',
                    'Time range does not meet the selected service requirements.',
                );

                return;
            }

            $this->timeRange = [
                'start' => $start,
                'end' => $end,
                'service_types' => $serviceTypes,
            ];
        });
    }

    /**
     * @return array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     service_types: list<string>
     * }|null
     */
    public function timeRange(): ?array
    {
        return $this->timeRange;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Invalid search filters.',
                'details' => $validator->errors()->messages(),
                'request_id' => (string) Str::uuid(),
            ],
        ], 422));
    }
}
