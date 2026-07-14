<?php

namespace App\Services\Gsb;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

class CompanyRegistryRecordNormalizer
{
    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    public function normalize(array $response, string $nationalNumber): ?array
    {
        $values = $this->flatten($response);

        $entityName = $this->first($values, [
            'company_name_ar', 'company_arabic_name', 'company_name', 'company',
            'establishment_name_ar', 'establishment_name', 'registry_name', 'registery_name',
            'commercial_name', 'trade_name', 'comm_name', 'reg_name', 'name_ar', 'name',
        ]);

        if (! filled($entityName)) {
            return null;
        }

        return [
            'entity_name' => $entityName,
            'registration_number' => $nationalNumber,
            'company_registration_date' => $this->date($this->first($values, [
                'company_registration_date', 'registration_date', 'register_date', 'registry_date',
                'establishment_date', 'est_date', 'reg_date', 'regdate',
            ])),
            'company_capital' => $this->number($this->first($values, [
                'company_capital', 'registered_capital', 'capital_value', 'capital_amount', 'capital',
            ])),
            'organization_type' => $this->first($values, [
                'company_type_name', 'company_type', 'organization_type_name', 'organization_type',
                'establishment_type_name', 'establishment_type', 'registry_type', 'reg_type_name', 'type_name',
            ]),
            'governorate' => $this->first($values, [
                'governorate_name', 'company_governorate', 'establishment_governorate',
                'registry_governorate', 'gov_name', 'governorate',
            ]),
            'commercial_registration_number' => $this->first($values, [
                'commercial_registration_number', 'company_registration_number', 'registration_number',
                'registry_number', 'register_no', 'reg_number', 'reg_no',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     */
    private function first(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $values[$this->key($key)] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, scalar|null>
     */
    private function flatten(array $input): array
    {
        $values = [];

        array_walk_recursive($input, function (mixed $value, mixed $key) use (&$values): void {
            if (! is_scalar($value) && $value !== null) {
                return;
            }

            $normalizedKey = $this->key((string) $key);

            if ($normalizedKey !== '' && ! filled($values[$normalizedKey] ?? null) && filled($value)) {
                $values[$normalizedKey] = $value;
            }
        });

        return $values;
    }

    private function key(string $key): string
    {
        $key = trim($key);
        $key = $key === strtoupper($key) ? strtolower($key) : Str::snake($key);

        return trim((string) preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/i', '_', $key)), '_');
    }

    private function date(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return $value;
        }
    }

    private function number(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $number = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value)) ?: '';

        return is_numeric($number) ? $number : $value;
    }
}
