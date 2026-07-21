<?php

namespace App\Services\Gsb;

class SignFlowOpenService
{
    private const SERVICE = 'signflow_v2_open';

    public function __construct(
        private readonly GsbClient $client,
    ) {}

    public function isRunnable(): bool
    {
        return $this->client->isEnabled(self::SERVICE)
            && $this->client->hasPath(self::SERVICE)
            && $this->client->credentialsConfigured(self::SERVICE);
    }

    /** @return array<string, mixed> */
    public function introspect(string $accessToken): array
    {
        return $this->post('introspect', $accessToken);
    }

    /** @return array<string, mixed> */
    public function userInfo(string $accessToken): array
    {
        return $this->post('info/user', $accessToken);
    }

    /** @return array<string, mixed> */
    public function certificate(string $accessToken): array
    {
        return $this->post('info/x509', $accessToken);
    }

    /** @return array<string, mixed> */
    public function signatureInfo(string $accessToken): array
    {
        return $this->post('info/signature', $accessToken);
    }

    /** @return array<string, mixed> */
    public function logout(string $accessToken): array
    {
        return $this->post('logout', $accessToken);
    }

    /** @return array<string, mixed> */
    public function status(string $nationalId): array
    {
        $nationalId = preg_replace('/\D+/', '', $nationalId) ?: '';

        if (! preg_match('/^\d{10}$/', $nationalId)) {
            return ['ok' => false, 'error' => 'INVALID_NATIONAL_ID'];
        }

        if (! $this->isRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        $response = $this->client->requestPath(
            self::SERVICE,
            $this->operationPath('info/status/{nationalId}'),
            method: 'GET',
            pathParameters: ['nationalId' => $nationalId],
        );

        return $this->normalize($response);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function nationalId(array $result): ?string
    {
        foreach (['data.nationalId', 'data.national_id', 'data.data.nationalId', 'data.data.national_id'] as $path) {
            $value = preg_replace('/\D+/', '', (string) data_get($result, $path)) ?: '';

            if (preg_match('/^\d{10}$/', $value)) {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function post(string $operation, string $accessToken): array
    {
        if (! $this->isRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        $response = $this->client->requestPath(
            self::SERVICE,
            $this->operationPath($operation),
            ['access_token' => $accessToken],
            'POST',
        );

        return $this->normalize($response);
    }

    private function operationPath(string $operation): string
    {
        return rtrim((string) config('services.gsb.services.'.self::SERVICE.'.path'), '/')
            .'/'.ltrim($operation, '/');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function normalize(array $response): array
    {
        $json = $response['json'] ?? null;

        if (! ($response['ok'] ?? false) || ! is_array($json)) {
            $status = $response['status'] ?? null;

            return [
                'ok' => false,
                'error' => match ($status) {
                    401 => 'UNAUTHORIZED',
                    404 => 'NOT_FOUND',
                    default => $response['error'] ?? 'REQUEST_FAILED',
                },
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'data' => $json,
            'meta' => [
                'source' => 'gsb_signflow_v2_open',
                'status' => $response['status'] ?? null,
            ],
        ];
    }
}
