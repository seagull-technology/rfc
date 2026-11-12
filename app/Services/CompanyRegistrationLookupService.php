<?php

namespace App\Services;

use App\Services\Gsb\CcdCompanyService;
use App\Services\Gsb\MitEstablishmentService;

class CompanyRegistrationLookupService
{
    public const SESSION_KEY = 'company_registration_lookup';

    public function __construct(
        private readonly CcdCompanyService $ccdCompanyService,
        private readonly MitEstablishmentService $mitEstablishmentService,
    ) {}

    public function requiresRegistrationDate(): bool
    {
        return $this->ccdCompanyService->requiresRegistrationDate()
            || $this->mitEstablishmentService->requiresRegistrationDate();
    }

    /** @return array<string, mixed> */
    public function lookup(string $registrationNumber, ?string $registrationDate = null): array
    {
        $registrationNumber = $this->normalizeRegistrationNumber($registrationNumber);

        if (! preg_match('/^\d{1,10}$/', $registrationNumber)) {
            return [
                'ok' => false,
                'error' => 'INVALID_REGISTRATION_NUMBER',
            ];
        }

        if (! $this->ccdCompanyService->isRunnable() && ! $this->mitEstablishmentService->isRunnable()) {
            if (app()->environment('production') || config('services.gsb.enabled')) {
                return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
            }

            return $this->localLookup($registrationNumber);
        }

        $attempts = [];

        foreach ($this->providers($registrationNumber, $registrationDate) as $provider) {
            if (! $provider['runnable']) {
                continue;
            }

            $result = $provider['lookup']();
            $attempts[] = [
                'source' => $provider['source'],
                'ok' => (bool) ($result['ok'] ?? false),
                'error' => $result['error'] ?? null,
                'status' => $result['status'] ?? data_get($result, 'meta.status'),
            ];

            if ($result['ok'] ?? false) {
                return [
                    'ok' => true,
                    'registration_number' => $registrationNumber,
                    'lookup_registration_date' => $registrationDate,
                    'data' => (array) ($result['data'] ?? []),
                    'meta' => array_merge((array) ($result['meta'] ?? []), [
                        'attempts' => $attempts,
                    ]),
                ];
            }
        }

        $hasTechnicalFailure = collect($attempts)->contains(
            fn (array $attempt): bool => ! in_array($attempt['error'] ?? null, ['NOT_FOUND', 'SERVICE_DISABLED'], true),
        );

        return [
            'ok' => false,
            'error' => $hasTechnicalFailure ? 'LOOKUP_FAILED' : 'NOT_FOUND',
            'meta' => ['attempts' => $attempts],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $lookupState
     */
    public function lookupStateMatches(?array $lookupState, string $registrationNumber, ?string $registrationDate = null): bool
    {
        return (bool) ($lookupState['ok'] ?? false)
            && ($lookupState['registration_number'] ?? null) === $this->normalizeRegistrationNumber($registrationNumber)
            && (! $this->requiresRegistrationDate() || (
                filled($registrationDate) && ($lookupState['lookup_registration_date'] ?? null) === $registrationDate
            ))
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
            'lookup_registration_date' => $lookup['lookup_registration_date'] ?? null,
            'data' => (array) ($lookup['data'] ?? []),
            'meta' => (array) ($lookup['meta'] ?? []),
            'verified_at' => now()->toDateTimeString(),
        ];
    }

    private function normalizeRegistrationNumber(string $registrationNumber): string
    {
        return preg_replace('/\D+/', '', $registrationNumber) ?: '';
    }

    /** @return array<int, array{source:string,runnable:bool,lookup:callable():array<string,mixed>}> */
    private function providers(string $registrationNumber, ?string $registrationDate): array
    {
        return [
            [
                'source' => 'gsb_ccd_company',
                'runnable' => $this->ccdCompanyService->isRunnable(),
                'lookup' => fn (): array => $this->ccdCompanyService->lookup($registrationNumber, $registrationDate),
            ],
            [
                'source' => 'gsb_mit_services',
                'runnable' => $this->mitEstablishmentService->isRunnable(),
                'lookup' => fn (): array => $this->mitEstablishmentService->lookup($registrationNumber, $registrationDate),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function localLookup(string $registrationNumber): array
    {
        return [
            'ok' => true,
            'registration_number' => $registrationNumber,
            'data' => $this->localCompany($registrationNumber),
            'meta' => ['source' => 'local_mock'],
        ];
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
            'organization_type' => 'Production company',
            'governorate' => 'Amman',
        ];
    }
}
