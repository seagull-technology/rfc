<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OrganizationRegistrationLookupService
{
    public const SESSION_KEY = 'organization_registration_lookup';

    public function __construct(
        private readonly GovernmentCompanyRegistryClient $client,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->client->isEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $nationalId, ?string $registrationNumber = null): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'error' => 'SERVICE_DISABLED',
            ];
        }

        $nationalId = trim($nationalId);
        $registrationNumber = $this->normalizeCandidate($registrationNumber);

        $result = $this->client->lookupByNationalId($nationalId);

        if (! ($result['ok'] ?? false) || ! is_array($result['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => filled($result['error'] ?? null) ? 'CONNECTION_FAILED' : 'LOOKUP_FAILED',
                'technical_message' => $result['error'] ?? null,
            ];
        }

        $payload = $result['json'];
        $name = trim((string) data_get($payload, 'CompanyInfo.Company'));

        if ($name === '') {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
            ];
        }

        $registrationCandidates = $this->registrationCandidates($payload);

        if ($registrationNumber && $registrationCandidates !== [] && ! $this->matchesCandidate($registrationNumber, $registrationCandidates)) {
            return [
                'ok' => false,
                'error' => 'REGISTRATION_MISMATCH',
                'registration_candidates' => $registrationCandidates,
            ];
        }

        $primaryRegistrationNumber = $registrationCandidates[0] ?? null;

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'registration_candidates' => $registrationCandidates,
            'data' => [
                'organization_name' => $name,
                'organization_registration_no' => $primaryRegistrationNumber,
                'organization_email' => trim((string) data_get($payload, 'CompanyInfo.Email')) ?: null,
                'organization_phone' => trim((string) data_get($payload, 'CompanyInfo.Mobile')) ?: null,
            ],
            'meta' => [
                'company_type' => data_get($payload, 'CompanyInfo.Type'),
                'raw_company_info' => Arr::only((array) data_get($payload, 'CompanyInfo', []), [
                    'Company',
                    'Type',
                    'Mobile',
                    'Email',
                ]),
                'records_count' => count((array) data_get($payload, 'Files', [])),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     * @return array<string, mixed>
     */
    public function mergeLookupDataIntoInput(array $input, ?array $lookupState): array
    {
        if (! $lookupState || ! ($lookupState['ok'] ?? false)) {
            return $input;
        }

        $data = (array) ($lookupState['data'] ?? []);

        foreach (['organization_name', 'organization_registration_no', 'organization_email', 'organization_phone'] as $field) {
            if (blank($input[$field] ?? null) && filled($data[$field] ?? null)) {
                $input[$field] = $data[$field];
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     */
    public function lookupStateMatches(?array $lookupState, string $nationalId, ?string $registrationNumber = null): bool
    {
        if (! $lookupState || ! ($lookupState['ok'] ?? false)) {
            return false;
        }

        if (($lookupState['national_id'] ?? null) !== trim($nationalId)) {
            return false;
        }

        $registrationNumber = $this->normalizeCandidate($registrationNumber);
        if (! $registrationNumber) {
            return true;
        }

        return $this->matchesCandidate($registrationNumber, (array) ($lookupState['registration_candidates'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    public function sessionStateFromLookup(array $lookup): array
    {
        return [
            'ok' => (bool) ($lookup['ok'] ?? false),
            'national_id' => $lookup['national_id'] ?? null,
            'registration_candidates' => array_values((array) ($lookup['registration_candidates'] ?? [])),
            'data' => (array) ($lookup['data'] ?? []),
            'meta' => (array) ($lookup['meta'] ?? []),
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function registrationCandidates(array $payload): array
    {
        $candidates = [];

        foreach ((array) data_get($payload, 'Files', []) as $file) {
            $value = $this->normalizeCandidate(data_get($file, 'Table1Values.Registration Number'));

            if ($value) {
                $candidates[] = $value;
            }
        }

        foreach ([
            data_get($payload, 'CompanyInfo.RegistrationNumber'),
            data_get($payload, 'CompanyInfo.Registration Number'),
            data_get($payload, 'CompanyInfo.RegNo'),
        ] as $fallback) {
            $value = $this->normalizeCandidate($fallback);

            if ($value) {
                $candidates[] = $value;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function matchesCandidate(string $value, array $candidates): bool
    {
        $normalized = $this->normalizeCandidate($value);

        foreach ($candidates as $candidate) {
            if ($this->normalizeCandidate($candidate) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCandidate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::upper(preg_replace('/\s+/', '', $value));
    }
}
