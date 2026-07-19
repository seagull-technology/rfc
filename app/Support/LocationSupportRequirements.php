<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocationSupportRequirements
{
    public const PUBLIC_SECURITY_ENTITY_CODE = 'public-security-directorate';

    public const MILITARY_ENTITY_CODE = 'military-media-directorate';

    public const SCHEDULE_SHARED = 'shared';

    public const SCHEDULE_PER_LOCATION = 'per_location';

    /**
     * @param  array<int|string, array<string, mixed>>  $locations
     * @return array<int, array<string, mixed>>
     */
    public static function prepareLocations(array $locations): array
    {
        $usedKeys = [];

        return collect(array_values($locations))
            ->map(function (array $location, int $index) use (&$usedKeys): array {
                $candidate = trim((string) ($location['location_key'] ?? ''));
                $candidate = preg_replace('/[^A-Za-z0-9_-]/', '_', $candidate) ?: '';

                if (blank($candidate) || in_array($candidate, $usedKeys, true)) {
                    $candidate = 'location_'.($index + 1);
                }

                while (in_array($candidate, $usedKeys, true)) {
                    $candidate .= '_'.($index + 1);
                }

                $usedKeys[] = $candidate;
                $location['location_key'] = $candidate;

                return $location;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $annex
     * @param  array<int|string, array<string, mixed>>  $locations
     * @param  array<int|string, array<string, mixed>>|null  $submittedRequirements
     * @return array{locations: array<int, array<string, mixed>>, requirements: array<int, array<string, mixed>>}
     */
    public static function editingState(array $annex, array $locations, ?array $submittedRequirements = null): array
    {
        $locations = self::prepareLocations($locations);

        if ($submittedRequirements !== null) {
            $requirements = self::normalize($submittedRequirements, $locations, false);
        } elseif (is_array(data_get($annex, 'location_support_requirements'))) {
            $requirements = self::normalize((array) data_get($annex, 'location_support_requirements'), $locations, false);
        } else {
            $requirements = self::fromLegacy(
                $locations,
                (array) data_get($annex, 'public_security_support', []),
                (array) data_get($annex, 'military_support', []),
            );
        }

        if ($requirements === []) {
            $requirements[] = self::emptyRequirement();
        }

        return compact('locations', 'requirements');
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $requirements
     * @param  array<int, array<string, mixed>>  $locations
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $requirements, array $locations, bool $removeEmpty = true): array
    {
        $locationKeys = collect($locations)
            ->pluck('location_key')
            ->filter()
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();

        return collect(array_values($requirements))
            ->map(function (array $requirement) use ($locationKeys): array {
                $scheduleMode = in_array(
                    (string) ($requirement['schedule_mode'] ?? ''),
                    [self::SCHEDULE_SHARED, self::SCHEDULE_PER_LOCATION],
                    true,
                ) ? (string) $requirement['schedule_mode'] : self::SCHEDULE_SHARED;

                $assignments = collect((array) ($requirement['assignments'] ?? []))
                    ->map(function ($assignment): array {
                        $assignment = (array) $assignment;

                        return [
                            'location_key' => trim((string) ($assignment['location_key'] ?? '')),
                            'selected' => filter_var($assignment['selected'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'date' => self::nullableString($assignment['date'] ?? null),
                            'time_from' => self::nullableString($assignment['time_from'] ?? null),
                            'time_to' => self::nullableString($assignment['time_to'] ?? null),
                        ];
                    })
                    ->filter(fn (array $assignment): bool => $assignment['selected'] && in_array($assignment['location_key'], $locationKeys, true))
                    ->unique('location_key')
                    ->values()
                    ->all();

                return [
                    'requirement_key' => self::requirementKey($requirement['requirement_key'] ?? null),
                    'authority' => self::normalizeAuthorityCode($requirement['authority'] ?? null),
                    'authority_name_en' => self::nullableString($requirement['authority_name_en'] ?? null),
                    'authority_name_ar' => self::nullableString($requirement['authority_name_ar'] ?? null),
                    'requirement' => self::nullableString($requirement['requirement'] ?? null),
                    'requirement_name_en' => self::nullableString($requirement['requirement_name_en'] ?? null),
                    'requirement_name_ar' => self::nullableString($requirement['requirement_name_ar'] ?? null),
                    'notes' => self::nullableString($requirement['notes'] ?? null),
                    'schedule_mode' => $scheduleMode,
                    'shared_date' => self::nullableString($requirement['shared_date'] ?? null),
                    'shared_time_from' => self::nullableString($requirement['shared_time_from'] ?? null),
                    'shared_time_to' => self::nullableString($requirement['shared_time_to'] ?? null),
                    'assignments' => $assignments,
                ];
            })
            ->when($removeEmpty, fn ($rows) => $rows->filter(fn (array $row): bool => self::requirementHasData($row)))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $locations
     * @param  array<int|string, array<string, mixed>>  $publicSecurityRows
     * @param  array<int|string, array<string, mixed>>  $militaryRows
     * @return array<int, array<string, mixed>>
     */
    public static function fromLegacy(array $locations, array $publicSecurityRows = [], array $militaryRows = []): array
    {
        $groups = [];
        $hasNestedRows = false;

        foreach ($locations as $location) {
            foreach ((array) data_get($location, 'support_requirements', []) as $supportRequirement) {
                $supportRequirement = (array) $supportRequirement;

                if (! self::legacyRequirementHasData($supportRequirement)) {
                    continue;
                }

                $hasNestedRows = true;
                self::appendLegacyAssignment($groups, $supportRequirement, $location);
            }
        }

        if (! $hasNestedRows) {
            foreach ([
                'public_security' => $publicSecurityRows,
                'military' => $militaryRows,
            ] as $authority => $rows) {
                foreach ($rows as $supportRequirement) {
                    $supportRequirement = (array) $supportRequirement;
                    $location = self::locationForLegacyRow($locations, $supportRequirement);

                    if ($location === null || ! self::legacyRequirementHasData($supportRequirement)) {
                        continue;
                    }

                    $supportRequirement['authority'] = $authority;
                    self::appendLegacyAssignment($groups, $supportRequirement, $location);
                }
            }
        }

        return collect($groups)
            ->map(function (array $group): array {
                $schedules = collect($group['assignments'])
                    ->map(fn (array $assignment): string => implode('|', [
                        (string) ($assignment['date'] ?? ''),
                        (string) ($assignment['time_from'] ?? ''),
                        (string) ($assignment['time_to'] ?? ''),
                    ]))
                    ->unique();

                $group['schedule_mode'] = $schedules->count() <= 1
                    ? self::SCHEDULE_SHARED
                    : self::SCHEDULE_PER_LOCATION;

                if ($group['schedule_mode'] === self::SCHEDULE_SHARED && filled($group['assignments'][0] ?? null)) {
                    $group['shared_date'] = $group['assignments'][0]['date'] ?? null;
                    $group['shared_time_from'] = $group['assignments'][0]['time_from'] ?? null;
                    $group['shared_time_to'] = $group['assignments'][0]['time_to'] ?? null;
                }

                return $group;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $locations
     * @param  array<int, array<string, mixed>>  $requirements
     * @return array<int, array<string, mixed>>
     */
    public static function applyToLocations(array $locations, array $requirements): array
    {
        $locations = self::prepareLocations($locations);
        $requirements = self::normalize($requirements, $locations);

        foreach ($locations as &$location) {
            $location['support_requirements'] = [];
        }
        unset($location);

        $locationsByKey = collect($locations)->mapWithKeys(
            fn (array $location, int $index): array => [(string) $location['location_key'] => $index],
        );

        foreach ($requirements as $requirement) {
            foreach ($requirement['assignments'] as $assignment) {
                $locationIndex = $locationsByKey->get($assignment['location_key']);

                if ($locationIndex === null) {
                    continue;
                }

                $usesSharedSchedule = $requirement['schedule_mode'] === self::SCHEDULE_SHARED;
                $locations[$locationIndex]['support_requirements'][] = [
                    'authority' => $requirement['authority'],
                    'authority_name_en' => $requirement['authority_name_en'],
                    'authority_name_ar' => $requirement['authority_name_ar'],
                    'requirement' => $requirement['requirement'],
                    'requirement_name_en' => $requirement['requirement_name_en'],
                    'requirement_name_ar' => $requirement['requirement_name_ar'],
                    'date' => $usesSharedSchedule ? $requirement['shared_date'] : $assignment['date'],
                    'time_from' => $usesSharedSchedule ? $requirement['shared_time_from'] : $assignment['time_from'],
                    'time_to' => $usesSharedSchedule ? $requirement['shared_time_to'] : $assignment['time_to'],
                    'notes' => $requirement['notes'],
                    'requirement_key' => $requirement['requirement_key'],
                ];
            }
        }

        return $locations;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyRequirement(): array
    {
        return [
            'requirement_key' => 'requirement_'.Str::lower(Str::random(10)),
            'authority' => null,
            'authority_name_en' => null,
            'authority_name_ar' => null,
            'requirement' => null,
            'requirement_name_en' => null,
            'requirement_name_ar' => null,
            'notes' => null,
            'schedule_mode' => self::SCHEDULE_SHARED,
            'shared_date' => null,
            'shared_time_from' => null,
            'shared_time_to' => null,
            'assignments' => [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, mixed>  $supportRequirement
     * @param  array<string, mixed>  $location
     */
    private static function appendLegacyAssignment(array &$groups, array $supportRequirement, array $location): void
    {
        $authority = self::normalizeAuthorityCode($supportRequirement['authority'] ?? null);
        $requirement = self::nullableString($supportRequirement['requirement'] ?? null);
        $notes = self::nullableString($supportRequirement['notes'] ?? null);
        $signature = implode('|', [$authority, $requirement, $notes]);

        if (! isset($groups[$signature])) {
            $groups[$signature] = [
                'requirement_key' => self::requirementKey($supportRequirement['requirement_key'] ?? null),
                'authority' => $authority,
                'authority_name_en' => self::nullableString($supportRequirement['authority_name_en'] ?? null),
                'authority_name_ar' => self::nullableString($supportRequirement['authority_name_ar'] ?? null),
                'requirement' => $requirement,
                'requirement_name_en' => self::nullableString($supportRequirement['requirement_name_en'] ?? null),
                'requirement_name_ar' => self::nullableString($supportRequirement['requirement_name_ar'] ?? null),
                'notes' => $notes,
                'schedule_mode' => self::SCHEDULE_SHARED,
                'shared_date' => null,
                'shared_time_from' => null,
                'shared_time_to' => null,
                'assignments' => [],
            ];
        }

        $groups[$signature]['assignments'][] = [
            'location_key' => (string) ($location['location_key'] ?? ''),
            'selected' => true,
            'date' => self::nullableString($supportRequirement['date'] ?? null),
            'time_from' => self::nullableString($supportRequirement['time_from'] ?? null),
            'time_to' => self::nullableString($supportRequirement['time_to'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $locations
     * @param  array<string, mixed>  $legacyRow
     * @return array<string, mixed>|null
     */
    private static function locationForLegacyRow(array $locations, array $legacyRow): ?array
    {
        $locationName = trim((string) ($legacyRow['location'] ?? ''));

        return collect($locations)->first(
            fn (array $location): bool => filled($locationName)
                && trim((string) ($location['location_name'] ?? '')) === $locationName,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function requirementHasData(array $row): bool
    {
        return filled($row['authority'] ?? null)
            || filled($row['requirement'] ?? null)
            || filled($row['notes'] ?? null)
            || filled($row['shared_date'] ?? null)
            || collect((array) ($row['assignments'] ?? []))->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function legacyRequirementHasData(array $row): bool
    {
        return collect(Arr::only($row, ['authority', 'requirement', 'date', 'time_from', 'time_to', 'notes']))
            ->contains(fn ($value): bool => filled($value));
    }

    private static function requirementKey(mixed $value): string
    {
        $key = preg_replace('/[^A-Za-z0-9_-]/', '_', trim((string) $value)) ?: '';

        return filled($key) ? $key : 'requirement_'.Str::lower(Str::random(10));
    }

    public static function normalizeAuthorityCode(mixed $value): ?string
    {
        $code = self::nullableString($value);

        return match ($code) {
            'public_security' => self::PUBLIC_SECURITY_ENTITY_CODE,
            'military' => self::MILITARY_ENTITY_CODE,
            default => $code,
        };
    }

    public static function legacyAuthorityCode(mixed $value): ?string
    {
        return match (self::normalizeAuthorityCode($value)) {
            self::PUBLIC_SECURITY_ENTITY_CODE => 'public_security',
            self::MILITARY_ENTITY_CODE => 'military',
            default => null,
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return filled($value) ? $value : null;
    }
}
