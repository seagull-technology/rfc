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

        return Cache::remember(
            sprintf('gsb:cspd_personal_info_masked:%s', hash('sha256', $nationalId)),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($nationalId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function performLookup(string $nationalId): array
    {
        $response = $this->client->request('cspd_personal_info_masked', [
            'id' => $nationalId,
        ], 'GET');

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => (int) ($response['status'] ?? 0) === 404 ? 'NOT_FOUND' : ($response['error'] ?? 'LOOKUP_FAILED'),
                'status' => $response['status'] ?? null,
                'technical_message' => $response['error'] ?? null,
            ];
        }

        $json = $response['json'];
        $logicalStatus = (int) (data_get($json, 'code') ?? data_get($json, 'status') ?? 200);
        if ($logicalStatus === 404 || data_get($json, 'data.hasData') === false || data_get($json, 'hasData') === false) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
        }

        $record = $this->normalizer->firstRecord($json);

        if (! $record) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'status' => $response['status'] ?? null,
            ];
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
                'source' => 'gsb_cspd_personal_info_masked',
                'status' => $response['status'] ?? null,
            ],
        ];
    }
}
