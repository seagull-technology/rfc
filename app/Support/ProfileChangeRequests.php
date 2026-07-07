<?php

namespace App\Support;

use App\Models\Entity;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ProfileChangeRequests
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(Entity $entity): Collection
    {
        return collect((array) data_get($entity->metadata, 'profile_change_requests', []))
            ->sortByDesc(fn (array $request): string => (string) ($request['requested_at'] ?? ''))
            ->values();
    }

    public static function pending(Entity $entity): ?array
    {
        return self::all($entity)
            ->first(fn (array $request): bool => ($request['status'] ?? null) === 'pending');
    }

    /**
     * @return array<string, array{label:string,current:?string,type:string}>
     */
    public static function officialFields(Entity $entity): array
    {
        $fields = [
            'name_en' => [
                'label' => __('app.admin.entities.name_en'),
                'current' => $entity->name_en,
                'type' => 'text',
            ],
            'name_ar' => [
                'label' => __('app.admin.entities.name_ar'),
                'current' => $entity->name_ar,
                'type' => 'text',
            ],
        ];

        if ($entity->registration_type === 'student') {
            return [
                ...$fields,
                'national_id' => [
                    'label' => __('app.admin.users.national_id'),
                    'current' => $entity->national_id,
                    'type' => 'text',
                ],
                'birth_date' => [
                    'label' => __('app.auth.birth_date'),
                    'current' => data_get($entity->metadata, 'birth_date'),
                    'type' => 'date',
                ],
                'gender' => [
                    'label' => __('app.auth.gender'),
                    'current' => data_get($entity->metadata, 'gender'),
                    'type' => 'gender',
                ],
                'nationality' => [
                    'label' => __('app.auth.nationality'),
                    'current' => data_get($entity->metadata, 'nationality'),
                    'type' => 'text',
                ],
                'university_name' => [
                    'label' => __('app.auth.university_name'),
                    'current' => data_get($entity->metadata, 'university_name'),
                    'type' => 'text',
                ],
                'major' => [
                    'label' => __('app.auth.major'),
                    'current' => data_get($entity->metadata, 'major'),
                    'type' => 'text',
                ],
            ];
        }

        $fields = [
            ...$fields,
            'registration_no' => [
                'label' => __('app.auth.registration_number'),
                'current' => $entity->registration_no,
                'type' => 'text',
            ],
            'national_id' => [
                'label' => __('app.auth.organization_national_id'),
                'current' => $entity->national_id,
                'type' => 'text',
            ],
        ];

        if ($entity->registration_type === 'company') {
            $fields = [
                ...$fields,
                'company_registration_date' => [
                    'label' => __('app.auth.company_registration_date'),
                    'current' => data_get($entity->metadata, 'company_registration_date'),
                    'type' => 'date',
                ],
                'company_capital' => [
                    'label' => __('app.auth.company_capital'),
                    'current' => data_get($entity->metadata, 'company_capital'),
                    'type' => 'number',
                ],
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(Entity $entity): array
    {
        $rules = [
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'nationality' => ['nullable', 'string', 'max:120'],
            'university_name' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'company_registration_date' => ['nullable', 'date', 'before_or_equal:today'],
            'company_capital' => ['nullable', 'numeric', 'min:0'],
        ];

        return collect(array_keys(self::officialFields($entity)))
            ->mapWithKeys(fn (string $field): array => [$field => $rules[$field] ?? ['nullable', 'string', 'max:255']])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{label:string,current:?string,requested:?string}>
     */
    public static function buildChanges(Entity $entity, array $input): array
    {
        $changes = [];

        foreach (self::officialFields($entity) as $field => $definition) {
            $requested = self::normalizeValue($input[$field] ?? null);
            $current = self::normalizeValue($definition['current'] ?? null);

            if ($requested === $current) {
                continue;
            }

            $changes[$field] = [
                'label' => $definition['label'],
                'current' => $current,
                'requested' => $requested,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, array<string, mixed>>  $changes
     * @return array{0:array<string, mixed>,1:array<string, mixed>}
     */
    public static function splitApprovedPayload(array $changes): array
    {
        $columns = [];
        $metadata = [];

        foreach ($changes as $field => $change) {
            $value = self::normalizeValue($change['requested'] ?? null);

            if (in_array($field, ['name_en', 'name_ar', 'registration_no', 'national_id'], true)) {
                $columns[$field] = $value;

                continue;
            }

            $metadata[$field] = $value;
        }

        return [$columns, $metadata];
    }

    public static function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
