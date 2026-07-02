<?php

namespace App\Services;

use Illuminate\Support\Str;

class CompanyRegistrationLookupService
{
    public const SESSION_KEY = 'company_registration_lookup';

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $registrationNumber): array
    {
        $registrationNumber = $this->normalizeRegistrationNumber($registrationNumber);

        if ($registrationNumber === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_REGISTRATION_NUMBER',
            ];
        }

        return [
            'ok' => true,
            'registration_number' => $registrationNumber,
            'data' => $this->localCompany($registrationNumber),
            'meta' => [
                'source' => 'local_mock',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     */
    public function lookupStateMatches(?array $lookupState, string $registrationNumber): bool
    {
        return (bool) ($lookupState['ok'] ?? false)
            && ($lookupState['registration_number'] ?? null) === $this->normalizeRegistrationNumber($registrationNumber)
            && is_array($lookupState['data'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    public function sessionStateFromLookup(array $lookup): array
    {
        return [
            'ok' => (bool) ($lookup['ok'] ?? false),
            'registration_number' => $lookup['registration_number'] ?? null,
            'data' => (array) ($lookup['data'] ?? []),
            'meta' => (array) ($lookup['meta'] ?? []),
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    private function normalizeRegistrationNumber(string $registrationNumber): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($registrationNumber)) ?: '');
    }

    /**
     * @return array{entity_name:string,registration_number:string,company_registration_date:string,company_capital:string}
     */
    private function localCompany(string $registrationNumber): array
    {
        $hash = abs(crc32($registrationNumber));
        $year = 2014 + ($hash % 10);
        $month = (($hash >> 4) % 12) + 1;
        $day = (($hash >> 8) % 28) + 1;
        $capital = 50000 + (($hash % 40) * 2500);

        return [
            'entity_name' => 'RFC Production Company '.substr($registrationNumber, -4),
            'registration_number' => $registrationNumber,
            'company_registration_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'company_capital' => (string) $capital,
        ];
    }
}
