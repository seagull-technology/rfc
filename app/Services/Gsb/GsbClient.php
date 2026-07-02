<?php

namespace App\Services\Gsb;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GsbClient
{
    /**
     * @return array<string, mixed>
     */
    public function request(string $service, array $payload = [], ?string $method = null): array
    {
        $config = $this->serviceConfig($service);

        if (! $this->isEnabled($service)) {
            return $this->failure($service, 'SERVICE_DISABLED');
        }

        if (! $this->hasPath($service)) {
            return $this->failure($service, 'SERVICE_PATH_MISSING');
        }

        if (! $this->credentialsConfigured()) {
            return $this->failure($service, 'MISSING_CREDENTIALS');
        }

        $url = $this->url($service);
        $method = strtoupper($method ?: (string) ($config['method'] ?? 'POST'));

        try {
            $pendingRequest = Http::withOptions($this->httpOptions($url))
                ->withHeaders($this->headers())
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.gsb.timeout', 25))
                ->retry(2, 200, throw: false);

            $response = match ($method) {
                'GET' => $pendingRequest->get($url, $payload),
                'PUT' => $pendingRequest->put($url, $payload),
                'PATCH' => $pendingRequest->patch($url, $payload),
                default => $pendingRequest->post($url, $payload),
            };

            $json = $response->json();

            Log::channel('gov_lookup')->info('GSB request completed', [
                'service' => $service,
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'payload' => $this->safePayloadSummary($payload),
            ]);

            return [
                'ok' => $response->successful() && (is_array($json) || $json === null),
                'service' => $service,
                'method' => $method,
                'status' => $response->status(),
                'url' => $url,
                'json' => is_array($json) ? $json : null,
                'body' => $response->body(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::channel('gov_lookup')->error('GSB request failed', [
                'service' => $service,
                'method' => $method,
                'url' => $url,
                'message' => $exception->getMessage(),
                'payload' => $this->safePayloadSummary($payload),
            ]);

            return [
                'ok' => false,
                'service' => $service,
                'method' => $method,
                'status' => null,
                'url' => $url,
                'json' => null,
                'body' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function isEnabled(string $service): bool
    {
        $globalEnabled = (bool) config('services.gsb.enabled');
        $serviceEnabled = (bool) data_get($this->serviceConfig($service), 'enabled');

        return $globalEnabled && $serviceEnabled;
    }

    public function hasPath(string $service): bool
    {
        return filled(data_get($this->serviceConfig($service), 'path'));
    }

    public function credentialsConfigured(): bool
    {
        if (! (bool) config('services.gsb.send_modee_headers', true)) {
            return true;
        }

        return filled(config('services.gsb.client_id')) && filled(config('services.gsb.client_secret'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serviceSummaries(): array
    {
        $services = (array) config('services.gsb.services', []);

        return collect($services)
            ->map(function (array $config, string $key): array {
                return [
                    'key' => $key,
                    'enabled' => $this->isEnabled($key),
                    'path_configured' => filled($config['path'] ?? null),
                    'credentials_configured' => $this->credentialsConfigured(),
                    'callable' => $this->isEnabled($key) && $this->hasPath($key) && $this->credentialsConfigured(),
                    'method' => strtoupper((string) ($config['method'] ?? 'POST')),
                    'base_url' => (string) ($config['base_url'] ?? config('services.gsb.base_url')),
                    'path' => (string) ($config['path'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function runtimeSummary(): array
    {
        return [
            'enabled' => (bool) config('services.gsb.enabled'),
            'environment' => (string) config('services.gsb.environment', 'stg'),
            'base_url' => (string) config('services.gsb.base_url'),
            'client_id_configured' => filled(config('services.gsb.client_id')),
            'client_secret_configured' => filled(config('services.gsb.client_secret')),
            'modee_headers_enabled' => (bool) config('services.gsb.send_modee_headers', true),
            'ibm_headers_enabled' => (bool) config('services.gsb.send_ibm_headers', false),
            'force_ip_configured' => filled(config('services.gsb.force_ip')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceConfig(string $service): array
    {
        return (array) config("services.gsb.services.{$service}", []);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $service, string $error): array
    {
        return [
            'ok' => false,
            'service' => $service,
            'method' => strtoupper((string) data_get($this->serviceConfig($service), 'method', 'POST')),
            'status' => null,
            'url' => $this->hasPath($service) ? $this->url($service) : null,
            'json' => null,
            'body' => null,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $clientId = (string) config('services.gsb.client_id');
        $clientSecret = (string) config('services.gsb.client_secret');

        if ((bool) config('services.gsb.send_modee_headers', true)) {
            $headers['X-MODEE-Client-Id'] = $clientId;
            $headers['X-MODEE-Client-Secret'] = $clientSecret;
        }

        if ((bool) config('services.gsb.send_ibm_headers', false)) {
            $headers['X-IBM-Client-Id'] = $clientId;
            $headers['X-IBM-Client-Secret'] = $clientSecret;
        }

        $bearer = trim((string) config('services.gsb.bearer'));
        $basicUser = trim((string) config('services.gsb.basic_user'));
        $basicPass = trim((string) config('services.gsb.basic_pass'));

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
    private function httpOptions(string $url): array
    {
        $ip = trim((string) config('services.gsb.force_ip'));

        if ($ip === '') {
            return [];
        }

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;

        if (! $host) {
            return [];
        }

        return [
            'curl' => [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ];
    }

    private function url(string $service): string
    {
        $config = $this->serviceConfig($service);
        $path = (string) ($config['path'] ?? '');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? config('services.gsb.base_url')), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayloadSummary(array $payload): array
    {
        return collect(Arr::dot($payload))
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $this->maskValue($key, $value)])
            ->all();
    }

    private function maskValue(string $key, mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return '[complex]';
        }

        $value = (string) $value;

        if (preg_match('/(national|identity|id|no|number|phone|mobile)/i', $key) !== 1) {
            return mb_strlen($value) > 80 ? mb_substr($value, 0, 77).'...' : $value;
        }

        if (mb_strlen($value) <= 4) {
            return str_repeat('*', mb_strlen($value));
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
