<?php

namespace App\Support;

use Illuminate\Support\Arr;

final class MinistryInteriorPersonalDetails
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        $values = is_array($value) ? $value : [];

        if ($values === []) {
            return [];
        }

        if (! array_is_list($values)) {
            $values = [$values];
        }

        return collect($values)
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => $row)
            ->values()
            ->all();
    }

    public static function hasSubmittedData(array $row): bool
    {
        $submitted = Arr::except($row, [
            'confirmed',
            'signature',
            'signed_at',
            'signed_by_user_id',
        ]);

        return collect(Arr::dot($submitted))
            ->contains(fn ($value): bool => filled($value));
    }

    public static function isConfirmed(array $row): bool
    {
        return filter_var($row['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function hasAnyConfirmed(mixed $value): bool
    {
        return collect(self::rows($value))
            ->contains(fn (array $row): bool => self::isConfirmed($row));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeForStorage(mixed $value): array
    {
        return collect(self::rows($value))
            ->map(fn (array $row): array => self::normalizeArray($row))
            ->filter(fn (array $row): bool => self::hasSubmittedData($row) || self::isConfirmed($row))
            ->values()
            ->all();
    }

    public static function displayName(array $row): string
    {
        $parts = collect(['first_name', 'father_name', 'grandfather_name', 'family_name'])
            ->map(fn (string $field): string => trim((string) data_get($row, $field)))
            ->filter()
            ->values();

        return $parts->isNotEmpty()
            ? $parts->implode(' ')
            : trim((string) data_get($row, 'current_full_name'));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function normalizeArray(array $value): array
    {
        return collect($value)
            ->map(function ($fieldValue) {
                if (is_array($fieldValue)) {
                    return self::normalizeArray($fieldValue);
                }

                return is_string($fieldValue) && blank($fieldValue) ? null : $fieldValue;
            })
            ->all();
    }
}
