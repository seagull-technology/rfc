<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GovernmentCompanyRegistryClient
{
    /**
     * @return array<string, mixed>
     */
    public function lookupByNationalId(string $nationalId): array
    {
        $nationalId = trim($nationalId);

        return Cache::remember(
            sprintf('gov_company_registry:%s', $nationalId),
            now()->addMinutes(config('services.gov_company_registry.cache_minutes', 5)),
            fn (): array => $this->performLookup($nationalId),
        );
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.gov_company_registry.enabled')
            && filled(config('services.gov_company_registry.host'));
    }

    /**
     * @return array<string, mixed>
     */
    private function performLookup(string $nationalId): array
    {
        $url = $this->url();
        try {
            $response = Http::withOptions($this->httpOptions())
                ->withHeaders($this->headers())
                ->acceptJson()
                ->timeout((int) config('services.gov_company_registry.timeout', 25))
                ->retry(2, 200, throw: false)
                ->get($url, ['nationalId' => $nationalId]);

            $json = $response->json();

            Log::channel('gov_lookup')->info('Government company registry lookup', [
                'national_id' => $nationalId,
                'url' => $url,
                'status' => $response->status(),
            ]);

            return [
                'ok' => $response->successful() && is_array($json),
                'status' => $response->status(),
                'url' => $url,
                'json' => is_array($json) ? $json : null,
                'body' => $response->body(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::channel('gov_lookup')->error('Government company registry lookup failed', [
                'national_id' => $nationalId,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'url' => $url,
                'json' => null,
                'body' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(): array
    {
        $headers = ['Accept' => '*/*'];

        if (config('services.gov_company_registry.send_ibm_headers')) {
            $headers['X-IBM-Client-Id'] = (string) config('services.gov_company_registry.client_id');
            $headers['X-IBM-Client-Secret'] = (string) config('services.gov_company_registry.client_secret');
        }

        if (config('services.gov_company_registry.send_modee_headers')) {
            $headers['X-MODEE-Client-Id'] = (string) config('services.gov_company_registry.modee_client_id');
            $headers['X-MODEE-Client-Secret'] = (string) config('services.gov_company_registry.modee_client_secret');
        }

        $bearer = trim((string) config('services.gov_company_registry.bearer'));
        $basicUser = trim((string) config('services.gov_company_registry.basic_user'));
        $basicPass = trim((string) config('services.gov_company_registry.basic_pass'));

        if ($bearer !== '') {
            $headers['Authorization'] = 'Bearer '.$bearer;
        } elseif ($basicUser !== '' && $basicPass !== '') {
            $headers['Authorization'] = 'Basic '.base64_encode($basicUser.':'.$basicPass);
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function httpOptions(): array
    {
        $options = [];
        $host = (string) config('services.gov_company_registry.host');
        $port = (int) config('services.gov_company_registry.port', 9443);
        $ip = trim((string) config('services.gov_company_registry.ip'));

        if ($ip !== '') {
            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ];
        }

        return $options;
    }

    private function url(): string
    {
        $host = trim((string) config('services.gov_company_registry.host'));
        $port = (int) config('services.gov_company_registry.port', 9443);
        $path = '/'.ltrim((string) config('services.gov_company_registry.path'), '/');

        return sprintf('https://%s:%d%s', $host, $port, $path);
    }
}
