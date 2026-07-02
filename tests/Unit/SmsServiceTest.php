<?php

namespace Tests\Unit;

use App\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_sms_auth_connection_failure_returns_structured_failure(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::failedConnection('Connection timed out.'),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('auth_failed', $result['stage']);
        $this->assertSame('962791234567', $result['msisdn']);
    }

    public function test_sms_send_connection_failure_returns_structured_failure(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::put('gov_sms_token', 'test-token', now()->addMinutes(10));

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');

        Http::fake([
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::failedConnection('Connection timed out.'),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('send_exception', $result['stage']);
        $this->assertSame('962791234567', $result['msisdn']);
    }
}

