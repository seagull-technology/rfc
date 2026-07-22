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
    public function request(
        string $service,
        array $payload = [],
        ?string $method = null,
        array $pathParameters = [],
    ): array {
        $config = $this->serviceConfig($service);

        return $this->performRequest(
            $service,
            (string) ($config['path'] ?? ''),
            $payload,
            $method,
            $pathParameters,
        );
    }

    /**
     * Send a request to another operation exposed by the same GSB product.
     *
     * @return array<string, mixed>
     */
    public function requestPath(
        string $service,
        string $path,
        array $payload = [],
        ?string $method = null,
        array $pathParameters = [],
    ): array {
        return $this->performRequest($service, $path, $payload, $method, $pathParameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function performRequest(
        string $service,
        string $path,
        array $payload,
        ?string $method,
        array $pathParameters,
    ): array {
        $config = $this->serviceConfig($service);

        if (! $this->isEnabled($service)) {
            return $this->failure($service, 'SERVICE_DISABLED');
        }

        if (! filled($path)) {
            return $this->failure($service, 'SERVICE_PATH_MISSING');
        }

        if (! $this->credentialsConfigured($service)) {
            return $this->failure($service, 'MISSING_CREDENTIALS');
        }

        $url = $this->urlFromPath($service, $path, $pathParameters);
        $method = strtoupper($method ?: (string) ($config['method'] ?? 'POST'));

        try {
            $pendingRequest = Http::withOptions($this->httpOptions($url))
                ->withHeaders($this->headers($service))
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
                'url' => $this->safeUrl($url, $pathParameters),
                'status' => $response->status(),
                'payload' => $this->safePayloadSummary($payload),
            ]);

            return [
                'ok' => $response->successful() && (is_array($json) || $json === null),
                'service' => $service,
                'method' => $method,
                'status' => $response->status(),
                'url' => $this->safeUrl($url, $pathParameters),
                'json' => is_array($json) ? $json : null,
                'body' => $response->body(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::channel('gov_lookup')->error('GSB request failed', [
                'service' => $service,
                'method' => $method,
                'url' => $this->safeUrl($url, $pathParameters),
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

    public function credentialsConfigured(?string $service = null): bool
    {
        $serviceConfig = $service ? $this->serviceConfig($service) : [];
        $sendModeeHeaders = (bool) ($serviceConfig['send_modee_headers']
            ?? config('services.gsb.send_modee_headers', true));
        $sendIbmHeaders = (bool) ($serviceConfig['send_ibm_headers']
            ?? config('services.gsb.send_ibm_headers', false));

        if (! $sendModeeHeaders && ! $sendIbmHeaders) {
            return true;
        }

        return filled($this->clientId($serviceConfig)) && filled($this->clientSecret($serviceConfig));
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
                    'credentials_configured' => $this->credentialsConfigured($key),
                    'callable' => $this->isEnabled($key) && $this->hasPath($key) && $this->credentialsConfigured($key),
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
    private function headers(string $service): array
    {
        $serviceConfig = $this->serviceConfig($service);
        $headers = [
            'Accept' => (string) ($serviceConfig['accept'] ?? 'application/json'),
        ];
        $clientId = $this->clientId($serviceConfig);
        $clientSecret = $this->clientSecret($serviceConfig);
        $sendModeeHeaders = (bool) ($serviceConfig['send_modee_headers']
            ?? config('services.gsb.send_modee_headers', true));
        $sendIbmHeaders = (bool) ($serviceConfig['send_ibm_headers']
            ?? config('services.gsb.send_ibm_headers', false));

        if ($sendModeeHeaders) {
            $headers['X-MODEE-Client-Id'] = $clientId;
            $headers['X-MODEE-Client-Secret'] = $clientSecret;
        }

        if ($sendIbmHeaders) {
            $headers['X-IBM-Client-Id'] = $clientId;
            $headers['X-IBM-Client-Secret'] = $clientSecret;
        }

        $bearer = trim((string) ($serviceConfig['bearer'] ?? config('services.gsb.bearer')));
        $basicUser = trim((string) config('services.gsb.basic_user'));
        $basicPass = trim((string) config('services.gsb.basic_pass'));

        if ($bearer !== '') {
            $headers['Authorization'] = 'Bearer '.$bearer;
        } elseif ($basicUser !== '' && $basicPass !== '') {
            $headers['Authorization'] = 'Basic '.base64_encode($basicUser.':'.$basicPass);
        }

        return $headers;
    }

    /** @param array<string, mixed> $serviceConfig */
    private function clientId(array $serviceConfig): string
    {
        return trim((string) ($serviceConfig['client_id'] ?? config('services.gsb.client_id')));
    }

    /** @param array<string, mixed> $serviceConfig */
    private function clientSecret(array $serviceConfig): string
    {
        return trim((string) ($serviceConfig['client_secret'] ?? config('services.gsb.client_secret')));
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

    private function url(string $service, array $pathParameters = []): string
    {
        $config = $this->serviceConfig($service);

        return $this->urlFromPath($service, (string) ($config['path'] ?? ''), $pathParameters);
    }

    private function urlFromPath(string $service, string $path, array $pathParameters = []): string
    {
        $config = $this->serviceConfig($service);

        foreach ($pathParameters as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? config('services.gsb.base_url')), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function safeUrl(string $url, array $pathParameters): string
    {
        foreach ($pathParameters as $value) {
            $encoded = rawurlencode((string) $value);

            if ($encoded !== '') {
                $url = str_replace($encoded, '[masked]', $url);
            }
        }

        return $url;
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

        if (preg_match('/(token|secret|password|authorization|code|verifier|hash|signature)/i', $key) === 1) {
            return '[redacted]';
        }

        if (preg_match('/(national|identity|id|no|number|phone|mobile)/i', $key) !== 1) {
            return mb_strlen($value) > 80 ? mb_substr($value, 0, 77).'...' : $value;
        }

        if (mb_strlen($value) <= 4) {
            return str_repeat('*', mb_strlen($value));
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
