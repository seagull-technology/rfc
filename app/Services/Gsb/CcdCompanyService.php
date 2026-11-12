<?php

namespace App\Services\Gsb;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

class CcdCompanyService
{
    public function __construct(
        private readonly GsbClient $client,
        private readonly CompanyRegistryRecordNormalizer $normalizer,
    ) {}

    public function isRunnable(): bool
    {
        return $this->client->isEnabled('ccd_company')
            && $this->client->hasPath('ccd_company')
            && $this->client->credentialsConfigured();
    }

    public function requiresRegistrationDate(): bool
    {
        return str_ends_with(
            rtrim((string) config('services.gsb.services.ccd_company.path'), '/'),
            '/CompanybyNoAndRegDate',
        );
    }

    /** @return array<string, mixed> */
    public function lookup(string $nationalNumber, ?string $registrationDate = null): array
    {
        if (! $this->isRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        if ($this->requiresRegistrationDate()) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $registrationDate ?? '');
            if (! $date || $date->format('Y-m-d') !== $registrationDate) {
                return ['ok' => false, 'error' => 'INVALID_REGISTRATION_DATE'];
            }
        }

        $key = 'gsb:ccd_company:v2:'.hash('sha256', json_encode([
            config('services.gsb.base_url'), config('services.gsb.services.ccd_company'),
            $nationalNumber, $registrationDate,
        ]));
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $result = $this->performLookup($nationalNumber, $registrationDate);
        if ($result['ok'] ?? false) {
            Cache::put($key, $result, now()->addMinutes((int) config('services.gsb.cache_minutes', 10)));
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function performLookup(string $nationalNumber, ?string $registrationDate): array
    {
        $response = $this->client->request(
            'ccd_company',
            payload: $this->requiresRegistrationDate() ? [
                'NationalNo' => $nationalNumber,
                'RegDate' => DateTimeImmutable::createFromFormat('!Y-m-d', $registrationDate)->format('n/j/Y'),
            ] : [],
            pathParameters: ['nationalNo' => $nationalNumber],
        );

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            $status = $response['status'] ?? null;

            return [
                'ok' => false,
                'error' => $status === 404 ? 'NOT_FOUND' : ($response['error'] ?? 'LOOKUP_FAILED'),
                'status' => $status,
            ];
        }

        $data = $this->normalizer->normalize($response['json'], $nationalNumber);

        if ($data === null) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'status' => $response['status'] ?? null];
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => ['source' => 'gsb_ccd_company', 'status' => $response['status'] ?? null],
        ];
    }
}
