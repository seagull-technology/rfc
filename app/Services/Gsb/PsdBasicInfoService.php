<?php

namespace App\Services\Gsb;

use Illuminate\Support\Facades\Cache;

class PsdBasicInfoService
{
    public function __construct(
        private readonly GsbClient $client,
        private readonly PersonalInfoRecordNormalizer $normalizer,
    ) {}

    /** @return array<string, mixed> */
    public function lookup(string $individualNumber): array
    {
        $individualNumber = preg_replace('/\D+/', '', $individualNumber) ?: '';

        if (! preg_match('/^\d{1,20}$/', $individualNumber)) {
            return ['ok' => false, 'error' => 'INVALID_INDIVIDUAL_NUMBER'];
        }

        if (! $this->tokenServiceRunnable() && ! $this->maskedServiceRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        return Cache::remember(
            sprintf('gsb:psd_basic_info:%s', hash('sha256', $individualNumber)),
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($individualNumber),
        );
    }

    /** @return array<string, mixed> */
    private function performLookup(string $individualNumber): array
    {
        $attempts = [];

        if ($this->tokenServiceRunnable()) {
            $attempts[] = [
                'service' => 'psd_basic_info_token',
                'response' => $this->client->request('psd_basic_info_token', ['nationalNo' => $individualNumber], 'POST'),
            ];
        }

        if ($this->maskedServiceRunnable()) {
            $attempts[] = [
                'service' => 'cspd_personal_info_masked',
                'response' => $this->client->requestPath(
                    'cspd_personal_info_masked',
                    (string) config('services.gsb.services.cspd_personal_info_masked.psd_path'),
                    ['nationalNo' => $individualNumber],
                    'POST',
                ),
            ];
        }

        $lastFailure = ['ok' => false, 'error' => 'LOOKUP_FAILED'];

        foreach ($attempts as $attempt) {
            $result = $this->normalizeResponse($attempt['service'], $individualNumber, $attempt['response']);

            if (($result['ok'] ?? false) || ($result['error'] ?? null) === 'NOT_FOUND') {
                return $result;
            }

            $lastFailure = $result;
        }

        return $lastFailure;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalizeResponse(string $source, string $individualNumber, array $response): array
    {
        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => (int) ($response['status'] ?? 0) === 404 ? 'NOT_FOUND' : ($response['error'] ?? 'LOOKUP_FAILED'),
                'status' => $response['status'] ?? null,
            ];
        }

        $json = $response['json'];
        $logicalStatus = (int) (data_get($json, 'code') ?? data_get($json, 'status') ?? 200);

        if ($logicalStatus === 404 || data_get($json, 'data.hasData') === false || data_get($json, 'hasData') === false) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'status' => $response['status'] ?? null];
        }

        $record = $this->normalizer->firstRecord($json);
        $data = $record ? $this->normalizer->normalize($record) : [];

        if (! filled($data['full_name'] ?? null) && ! filled($data['birth_date'] ?? null)) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'status' => $response['status'] ?? null];
        }

        return [
            'ok' => true,
            'individual_number' => $individualNumber,
            'data' => $data,
            'meta' => ['source' => 'gsb_'.$source, 'status' => $response['status'] ?? null],
        ];
    }

    private function tokenServiceRunnable(): bool
    {
        return $this->client->isEnabled('psd_basic_info_token')
            && $this->client->hasPath('psd_basic_info_token')
            && $this->client->credentialsConfigured('psd_basic_info_token')
            && filled(config('services.gsb.services.psd_basic_info_token.bearer'));
    }

    private function maskedServiceRunnable(): bool
    {
        return $this->client->isEnabled('cspd_personal_info_masked')
            && filled(config('services.gsb.services.cspd_personal_info_masked.psd_path'))
            && $this->client->credentialsConfigured('cspd_personal_info_masked');
    }
}
