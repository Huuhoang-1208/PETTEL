<?php

namespace App\Domain\Listing;

final class AcceptanceRules
{
    private const SPECIES = ['DOG', 'CAT', 'OTHER'];

    private const SIZES = ['SMALL', 'MEDIUM', 'LARGE'];

    /** @return array<string, mixed> */
    public static function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['schema_version', 'species', 'sizes'],
            'properties' => [
                'schema_version' => ['type' => 'integer', 'enum' => [1]],
                'species' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => ['type' => 'string', 'enum' => self::SPECIES],
                ],
                'sizes' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => ['type' => 'string', 'enum' => self::SIZES],
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array{schema_version: 1, species: list<string>, sizes: list<string>}|null
     */
    public static function normalize(mixed $rules): ?array
    {
        if (! is_array($rules)) {
            return null;
        }

        $keys = array_keys($rules);
        sort($keys);

        if ($keys !== ['schema_version', 'sizes', 'species'] || ($rules['schema_version'] ?? null) !== 1) {
            return null;
        }

        foreach ([
            'species' => self::SPECIES,
            'sizes' => self::SIZES,
        ] as $key => $allowed) {
            $values = $rules[$key];

            if (! is_array($values) || ! array_is_list($values) || $values === []) {
                return null;
            }

            foreach ($values as $value) {
                if (! is_string($value) || ! in_array($value, $allowed, true)) {
                    return null;
                }
            }
        }

        return [
            'schema_version' => 1,
            'species' => $rules['species'],
            'sizes' => $rules['sizes'],
        ];
    }

    /** @param array{schema_version: 1, species: list<string>, sizes: list<string>} $rules */
    public static function accepts(array $rules, string $species, ?string $size): bool
    {
        return $size !== null
            && in_array($species, $rules['species'], true)
            && in_array($size, $rules['sizes'], true);
    }
}
