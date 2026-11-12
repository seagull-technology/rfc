<?php

namespace App\Services\Gsb;

use Illuminate\Support\Facades\Cache;

class CspdPersonalInfoService
{
    public function __construct(
        private readonly GsbClient $client,
        private readonly PersonalInfoRecordNormalizer $normalizer,
    ) {}

    public function isRunnable(): bool
    {
        if ($this->client->isEnabled('cspd_personal_info_token')) {
            return $this->client->hasPath('cspd_personal_info_token')
                && $this->client->credentialsConfigured('cspd_personal_info_token');
        }

        return $this->client->isEnabled('cspd_personal_info_masked')
            && $this->client->hasPath('cspd_personal_info_masked')
            && $this->client->credentialsConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $nationalId): array
    {
        $nationalId = preg_replace('/\D+/', '', $nationalId) ?: '';

        if (! preg_match('/^\d{10}$/', $nationalId)) {
            return [
                'ok' => false,
                'error' => 'INVALID_NATIONAL_ID',
            ];
        }

        if (! $this->isRunnable()) {
            return [
                'ok' => false,
                'error' => 'SERVICE_DISABLED',
            ];
        }

        if ($this->client->isEnabled('cspd_personal_info_token')) {
            if (blank(config('services.gsb.services.cspd_personal_info_token.bearer'))) {
                return ['ok' => false, 'error' => 'AUTHORIZATION_REQUIRED'];
            }

            // The token operation documents no id parameter; bind the returned identity below.
            // Do not reuse a cached identity after the token changes or expires.
            return $this->performLookup($nationalId, 'cspd_personal_info_token');
        }

        return Cache::remember(
            sprintf('gsb:cspd_personal_info_masked:%s', hash('sha256', $nationalId)),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($nationalId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function performLookup(string $nationalId, string $service = 'cspd_personal_info_masked'): array
    {
        $usesToken = $service === 'cspd_personal_info_token';
        $response = $this->client->request($service, $usesToken ? [] : ['id' => $nationalId], 'GET');

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => in_array((int) ($response['status'] ?? 0), [401, 403], true)
                    ? 'AUTHORIZATION_REQUIRED'
                    : ($usesToken ? 'TOKEN_LOOKUP_FAILED' : ($response['error'] ?? 'LOOKUP_FAILED')),
                'status' => $response['status'] ?? null,
                'technical_message' => $response['error'] ?? null,
            ];
        }

        $json = $response['json'];
        $logicalStatus = (int) (data_get($json, 'code') ?? data_get($json, 'status') ?? 200);
        if (in_array($logicalStatus, [401, 403], true)) {
            return ['ok' => false, 'error' => 'AUTHORIZATION_REQUIRED', 'status' => $response['status'] ?? null];
        }
        if ($logicalStatus === 404 || data_get($json, 'data.hasData') === false || data_get($json, 'hasData') === false) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        if ($usesToken && $logicalStatus >= 400) {
            return ['ok' => false, 'error' => 'TOKEN_LOOKUP_FAILED', 'status' => $response['status'] ?? null];
        }

        $record = $this->normalizer->firstRecord($json);

        if (! $record) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        if ($usesToken && ! $this->matchesNationalId($record, $nationalId)) {
            // Never attach the token owner's data to a different crew identifier.
            return ['ok' => false, 'error' => 'IDENTITY_NOT_CONFIRMED', 'status' => $response['status'] ?? null];
        }

        $data = $this->normalizer->normalize($record);

        if (! filled($data['full_name'] ?? null) && ! filled($data['birth_date'] ?? null)) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'national_id' => $nationalId,
            'data' => $data,
            'meta' => [
                'source' => 'gsb_'.$service,
                'status' => $response['status'] ?? null,
            ],
        ];
    }

    /** @param array<string, mixed> $record */
    private function matchesNationalId(array $record, string $nationalId): bool
    {
        foreach ($record as $key => $value) {
            $normalizedKey = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $key));

            if (in_array($normalizedKey, ['nationalid', 'nationalno', 'nationalnumber', 'nationalidnumber', 'natno', 'natnumber'], true)
                && is_scalar($value)
                && hash_equals($nationalId, trim((string) $value))) {
                return true;
            }
        }

        return false;
    }
}
