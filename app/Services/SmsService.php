<?php

namespace App\Services;

use App\Support\ApprovedOutboundUrl;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    private const TOKEN_CACHE_KEY = 'gov_sms_token';

    private const SUCCESS_CODE = 'E001';

    private readonly ApprovedOutboundUrl $approvedOutboundUrl;

    public function __construct(?ApprovedOutboundUrl $approvedOutboundUrl = null)
    {
        $this->approvedOutboundUrl = $approvedOutboundUrl ?? app(ApprovedOutboundUrl::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function send(string $text, string $to): array
    {
        $msisdn = PhoneNumber::normalize($to);

        if ($msisdn === '') {
            return ['ok' => false, 'stage' => 'invalid_msisdn', 'http' => null, 'raw' => null, 'msisdn' => ''];
        }

        if ($this->shouldSimulate()) {
            Log::channel('sms')->info('SMS simulated', [
                'to' => $to,
                'msisdn' => $msisdn,
                'text_len' => mb_strlen($text),
            ]);

            return ['ok' => true, 'stage' => 'simulated', 'http' => 200, 'raw' => null, 'msisdn' => $msisdn];
        }

        if (! $this->approvedOutboundUrl->isAllowed((string) config('services.gov_sms.base'))) {
            return ['ok' => false, 'stage' => 'unapproved_destination', 'http' => null, 'raw' => null, 'msisdn' => $msisdn];
        }

        $token = $this->getToken();

        if (! $token) {
            return ['ok' => false, 'stage' => 'auth_failed', 'http' => null, 'raw' => null, 'msisdn' => $msisdn];
        }

        $payload = [
            'data1' => [
                'msisdn' => $msisdn,
                'text' => $text,
                'header' => config('services.gov_sms.header'),
                'messageTypeId' => config('services.gov_sms.message_type_id'),
            ],
        ];

        try {
            $response = $this->sendRequest($token, $payload);

            if ($this->isUnauthorized($response)) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $token = $this->getToken();

                if (! $token) {
                    return ['ok' => false, 'stage' => 'auth_failed', 'http' => null, 'raw' => null, 'msisdn' => $msisdn];
                }

                $response = $this->sendRequest($token, $payload);
            }
        } catch (Throwable $exception) {
            Log::channel('sms')->warning('SMS send failed before response', [
                'base' => config('services.gov_sms.base'),
                'to' => $to,
                'msisdn' => $msisdn,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'stage' => 'send_exception',
                'http' => null,
                'raw' => $exception->getMessage(),
                'msisdn' => $msisdn,
            ];
        }

        $providerResponse = $this->providerResponse($response);
        $providerCode = $providerResponse['code'];
        $accepted = $response->successful() && $providerCode === self::SUCCESS_CODE;
        $stage = $accepted
            ? 'sent'
            : ($response->successful() ? 'unexpected_response' : 'send_failed');

        Log::channel('sms')->info('SMS send', [
            'base' => config('services.gov_sms.base'),
            'to' => $to,
            'msisdn' => $msisdn,
            'status' => $response->status(),
            'provider_code' => $providerCode,
        ]);

        return [
            'ok' => $accepted,
            'stage' => $stage,
            'http' => $response->status(),
            'raw' => $response->body(),
            'msisdn' => $msisdn,
            'provider_code' => $providerCode,
            'provider_message' => $providerResponse['message'],
        ];
    }

    private function shouldSimulate(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return app()->environment('local')
            && (! config('services.gov_sms.username') || ! config('services.gov_sms.password'));
    }

    private function getToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addSeconds($this->tokenCacheSeconds()), function () {
            try {
                $response = $this->request()
                    ->post('/authenticate', [
                        'username' => config('services.gov_sms.username'),
                        'password' => config('services.gov_sms.password'),
                    ]);
            } catch (Throwable $exception) {
                Log::channel('sms')->warning('SMS auth failed before response', [
                    'base' => config('services.gov_sms.base'),
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }

            if (! $response->ok()) {
                Log::channel('sms')->warning('SMS authentication rejected', [
                    'base' => config('services.gov_sms.base'),
                    'status' => $response->status(),
                ]);

                return null;
            }

            $token = $response->json('token');

            if (! is_string($token) || trim($token) === '') {
                Log::channel('sms')->warning('SMS authentication response did not contain a token', [
                    'base' => config('services.gov_sms.base'),
                    'status' => $response->status(),
                ]);

                return null;
            }

            return trim($token);
        });
    }

    private function request(): PendingRequest
    {
        return Http::withOptions(['allow_redirects' => false])
            ->baseUrl(rtrim((string) config('services.gov_sms.base'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('services.gov_sms.connect_timeout', 5)))
            ->timeout(max(1, (int) config('services.gov_sms.timeout', 15)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendRequest(string $token, array $payload): Response
    {
        return $this->request()
            ->withHeaders(['Authorization' => $this->authorizationHeader($token)])
            ->post('/sendSmsNotifications', $payload);
    }

    private function authorizationHeader(string $token): string
    {
        $token = trim($token);

        if (preg_match('/^Bearer\s+(.+)$/is', $token, $matches) === 1) {
            return 'Bearer '.preg_replace('/\s+/', '', $matches[1]);
        }

        return 'Bearer '.$token;
    }

    private function isUnauthorized(Response $response): bool
    {
        return $response->status() === 401
            || str_contains(strtolower($response->body()), 'unauthorized');
    }

    /**
     * @return array{code: ?string, message: ?string}
     */
    private function providerResponse(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json) || $json === []) {
            return ['code' => null, 'message' => null];
        }

        $code = array_key_first($json);
        $message = $code !== null ? $json[$code] ?? null : null;

        return [
            'code' => is_string($code) ? $code : null,
            'message' => is_scalar($message) ? (string) $message : null,
        ];
    }

    private function tokenCacheSeconds(): int
    {
        return min(55, max(1, (int) config('services.gov_sms.token_cache_seconds', 45)));
    }
}
