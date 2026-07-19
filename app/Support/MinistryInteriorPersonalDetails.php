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
            ->map(fn (array $row): array => collect($row)
                ->map(fn ($fieldValue) => is_string($fieldValue) && blank($fieldValue) ? null : $fieldValue)
                ->all())
            ->filter(fn (array $row): bool => self::hasSubmittedData($row) || self::isConfirmed($row))
            ->values()
            ->all();
    }
}
