<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
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

        $token = $this->getToken();

        if (! $token) {
            return ['ok' => false, 'stage' => 'auth_failed', 'http' => null, 'raw' => null, 'msisdn' => $msisdn];
        }

        $payload = [
            'data0' => [
                'msisdn' => $msisdn,
                'text' => $text,
                'header' => config('services.gov_sms.header'),
                'messageTypeId' => config('services.gov_sms.message_type_id'),
            ],
        ];

        $response = Http::baseUrl(config('services.gov_sms.base'))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 200, throw: false)
            ->withHeaders(['Authorization' => $token])
            ->post('/sendSmsNotifications', $payload);

        if ($response->status() === 401 || str_contains(strtolower($response->body()), 'unauthorized')) {
            $response = Http::baseUrl(config('services.gov_sms.base'))
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 200, throw: false)
                ->withToken($token)
                ->post('/sendSmsNotifications', $payload);
        }

        Log::channel('sms')->info('SMS send', [
            'base' => config('services.gov_sms.base'),
            'to' => $to,
            'msisdn' => $msisdn,
            'status' => $response->status(),
        ]);

        return [
            'ok' => $response->successful(),
            'stage' => 'sent',
            'http' => $response->status(),
            'raw' => $response->body(),
            'msisdn' => $msisdn,
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
        return Cache::remember('gov_sms_token', now()->addMinutes(10), function () {
            $response = Http::baseUrl(config('services.gov_sms.base'))
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 200, throw: false)
                ->post('/authenticate', [
                    'username' => config('services.gov_sms.username'),
                    'password' => config('services.gov_sms.password'),
                ]);

            if (! $response->ok()) {
                return null;
            }

            return $response->json('token');
        });
    }
}
