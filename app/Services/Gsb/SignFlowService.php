<?php

namespace App\Services\Gsb;

class SignFlowService
{
    private const SERVICE = 'signflow_v2';

    public function __construct(
        private readonly GsbClient $client,
    ) {}

    public function isRunnable(): bool
    {
        return $this->client->isEnabled(self::SERVICE)
            && $this->client->hasPath(self::SERVICE)
            && $this->client->credentialsConfigured(self::SERVICE);
    }

    public function isAuthorizationConfigured(): bool
    {
        return $this->isRunnable()
            && filled($this->authorizationUrl())
            && filled($this->clientId())
            && filled($this->clientSecret());
    }

    public function authorizationUrl(): string
    {
        return trim((string) config('services.sanad.authorization_url'));
    }

    public function clientId(): string
    {
        return trim((string) config('services.sanad.client_id'));
    }

    public function clientSecret(): string
    {
        return trim((string) config('services.sanad.client_secret'));
    }

    public function redirectUri(string $fallback): string
    {
        return trim((string) config('services.sanad.redirect_uri')) ?: $fallback;
    }

    public function scope(): string
    {
        return trim((string) config('services.sanad.scope', 'openid'));
    }

    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code, string $verifier, string $redirectUri): array
    {
        return $this->send('token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'verifier' => $verifier,
        ]);
    }

    /** @return array<string, mixed> */
    public function refresh(string $refreshToken): array
    {
        return $this->send('refresh', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /** @return array<string, mixed> */
    public function sign(string $accessToken, string $hash): array
    {
        return $this->send('sign', [
            'access_token' => $accessToken,
            'hash' => $hash,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $operation, array $payload): array
    {
        if (! $this->isRunnable()) {
            return ['ok' => false, 'error' => 'SERVICE_DISABLED'];
        }

        $response = $this->client->requestPath(
            self::SERVICE,
            $this->operationPath($operation),
            $payload,
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
                'error' => $status === 401 ? 'UNAUTHORIZED' : ($response['error'] ?? 'REQUEST_FAILED'),
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'data' => $json,
            'meta' => [
                'source' => 'gsb_signflow_v2',
                'status' => $response['status'] ?? null,
            ],
        ];
    }
}
