<?php

namespace App\Services\Gsb;

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
            && $this->client->credentialsConfigured();
    }

    /** @return array<string, mixed> */
    public function lookup(string $nationalNumber): array
    {
        if (! $this->isRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        return Cache::remember(
            'gsb:mit_establishment:'.$nationalNumber,
            now()->addMinutes((int) config('services.gsb.cache_minutes', 10)),
            fn (): array => $this->performLookup($nationalNumber),
        );
    }

    /** @return array<string, mixed> */
    private function performLookup(string $nationalNumber): array
    {
        $response = $this->client->request('mit_services', ['nationalNo' => $nationalNumber]);

        if (! ($response['ok'] ?? false) || ! is_array($response['json'] ?? null)) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'LOOKUP_FAILED',
                'status' => $response['status'] ?? null,
            ];
        }

        $data = $this->normalizer->normalize($response['json'], $nationalNumber);

        if ($data === null) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'status' => $response['status'] ?? null];
        }

        return [
            'ok' => true,
            'data' => $data,
            'meta' => ['source' => 'gsb_mit_services', 'status' => $response['status'] ?? null],
        ];
    }
}
