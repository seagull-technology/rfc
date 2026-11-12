<?php

namespace App\Services\Gsb;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

class MitEstablishmentService
{
    public function __construct(
        private readonly GsbClient $client,
        private readonly CompanyRegistryRecordNormalizer $normalizer,
    ) {}

    public function isRunnable(): bool
    {
        return $this->client->isEnabled('mit_services')
            && $this->client->hasPath('mit_services')
            && $this->client->credentialsConfigured('mit_services');
    }

    public function requiresRegistrationDate(): bool
    {
        return str_ends_with(
            rtrim((string) config('services.gsb.services.mit_services.path'), '/'),
            '/GetIndividualInfo',
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

        $key = 'gsb:mit_individual_registry:v3:'.hash('sha256', json_encode([
            config('services.gsb.base_url'), config('services.gsb.services.mit_services'),
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
        $response = $this->client->request('mit_services', $this->requiresRegistrationDate() ? [
            'Establishment_ID' => $nationalNumber,
            'REG_Date' => $registrationDate,
        ] : ['nationalNo' => $nationalNumber]);

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            $status = $response['status'] ?? null;

            return [
                'ok' => false,
                'error' => $status === 404 && ! isset($response['json']['httpCode'])
                    ? 'NOT_FOUND' : ($response['error'] ?? 'LOOKUP_FAILED'),
                'status' => $status,
            ];
        }

        $data = $this->normalizeRecord($response['json'], $nationalNumber);

        if ($data === null) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'status' => $response['status'] ?? null];
        }

        $data['organization_type'] ??= 'مؤسسة فردية';

        return [
            'ok' => true,
            'data' => $data,
            'meta' => ['source' => 'gsb_mit_services', 'status' => $response['status'] ?? null],
        ];
    }

    /** @param array<string, mixed> $response */
    private function normalizeRecord(array $response, string $nationalNumber): ?array
    {
        if ($this->requiresRegistrationDate() && is_array($response['data'] ?? null)) {
            // GetIndividualInfo uses mixed-case fields such as comM_NAME and reG_DATE_A.
            $record = array_change_key_case($response['data'], CASE_LOWER);

            if (array_key_exists('comm_name', $record) || array_key_exists('regcn', $record)) {
                $response = [
                    'company_name' => $record['comm_name'] ?? null,
                    'registry_name' => $record['regcn'] ?? null,
                    'registration_date' => $record['reg_date_a'] ?? $record['reg_date_e'] ?? null,
                    'company_capital' => $record['current_capital'] ?? $record['reg_capital'] ?? null,
                    'governorate' => $record['governorate'] ?? null,
                    'commercial_registration_number' => $record['reg_no'] ?? null,
                    'registry_status' => $record['status'] ?? null,
                ];
            }
        }

        return $this->normalizer->normalize($response, $nationalNumber);
    }
}
