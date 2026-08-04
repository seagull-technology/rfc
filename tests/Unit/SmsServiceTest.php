<?php

namespace Tests\Unit;

use App\Services\SmsService;
use Illuminate\Http\Client\Request;
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

    public function test_sms_uses_the_documented_payload_and_bearer_token(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');
        config()->set('services.gov_sms.header', 'RFC');
        config()->set('services.gov_sms.message_type_id', 3);

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::response(['token' => 'Bearer test-token'], 200),
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::response([
                'E001' => 'mobile number[962791234567] messagesId[12345]',
            ], 200),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertTrue($result['ok']);
        $this->assertSame('sent', $result['stage']);
        $this->assertSame('E001', $result['provider_code']);
        $this->assertSame('mobile number[962791234567] messagesId[12345]', $result['provider_message']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://bulk-sms.gov.jo/authenticate'
                && $request['username'] === 'user'
                && $request['password'] === 'pass';
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://bulk-sms.gov.jo/sendSmsNotifications'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && isset($request['data1'])
                && ! isset($request['data0'])
                && $request['data1']['msisdn'] === '962791234567'
                && $request['data1']['text'] === 'RFC test'
                && $request['data1']['header'] === 'RFC'
                && $request['data1']['messageTypeId'] === 3;
        });
    }

    public function test_sms_reauthenticates_once_without_duplicating_the_bearer_prefix(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::sequence()
                ->push(['token' => 'Bearer expired-token'], 200)
                ->push(['token' => 'fresh-token'], 200),
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::sequence()
                ->push(['error' => 'unauthorized'], 401)
                ->push(['E001' => 'mobile number[962791234567] messagesId[67890]'], 200),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertTrue($result['ok']);
        $this->assertSame('E001', $result['provider_code']);
        Http::assertSentCount(4);

        $authorizationHeaders = Http::recorded(
            fn (Request $request): bool => $request->url() === 'https://bulk-sms.gov.jo/sendSmsNotifications'
        )->map(fn (array $record): ?string => $record[0]->header('Authorization')[0] ?? null)->values()->all();

        $this->assertSame(['Bearer expired-token', 'Bearer fresh-token'], $authorizationHeaders);
    }

    public function test_sms_token_cache_expires_before_the_documented_one_minute_lifetime(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');
        config()->set('services.gov_sms.token_cache_seconds', 45);

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::sequence()
                ->push(['token' => 'Bearer token-one'], 200)
                ->push(['token' => 'Bearer token-two'], 200),
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::response([
                'E001' => 'mobile number[962791234567] messagesId[12345]',
            ], 200),
        ]);

        $this->assertTrue(app(SmsService::class)->send('First', '0791234567')['ok']);

        $this->travel(46)->seconds();

        $this->assertTrue(app(SmsService::class)->send('Second', '0791234567')['ok']);

        $authenticationRequests = Http::recorded(
            fn (Request $request): bool => $request->url() === 'https://bulk-sms.gov.jo/authenticate'
        );

        $this->assertCount(2, $authenticationRequests);
    }

    public function test_sms_rejects_an_undocumented_success_response(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::response(['token' => 'Bearer test-token'], 200),
            'https://bulk-sms.gov.jo/sendSmsNotifications' => Http::response(['ok' => true], 200),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('unexpected_response', $result['stage']);
        $this->assertSame('ok', $result['provider_code']);
    }

    public function test_sms_does_not_retry_a_rejected_authentication_request(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        Cache::forget('gov_sms_token');

        config()->set('services.gov_sms.base', 'https://bulk-sms.gov.jo');
        config()->set('services.gov_sms.username', 'user');
        config()->set('services.gov_sms.password', 'pass');

        Http::fake([
            'https://bulk-sms.gov.jo/authenticate' => Http::response([], 400),
        ]);

        $result = app(SmsService::class)->send('RFC test', '0791234567');

        $this->assertFalse($result['ok']);
        $this->assertSame('auth_failed', $result['stage']);
        Http::assertSentCount(1);
    }
}
