<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceTrustedHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRequestBoundaryTest extends TestCase
{
    public function test_a_trusted_forwarded_host_cannot_hide_an_untrusted_original_host(): void
    {
        config([
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['rfc.test'],
            'security.trusted_proxies' => ['10.0.40.81'],
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.40.81'])
            ->withHeaders(['X-Forwarded-Host' => 'rfc.test'])
            ->get('http://attacker.example/sign-in')
            ->assertBadRequest()
            ->assertHeaderMissing('Location');
    }

    public function test_every_forwarded_host_header_value_is_checked(): void
    {
        config([
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['rfc.test'],
        ]);

        $request = Request::create('http://rfc.test/sign-in');
        $request->headers->set('X-Original-Host', ['rfc.test', 'attacker.example']);

        $response = app(EnforceTrustedHosts::class)->handle(
            $request,
            fn () => response('Request reached the application'),
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_unsupported_forwarded_header_is_rejected_consistently_with_iis(): void
    {
        config([
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['rfc.test'],
        ]);

        foreach (['host=""', 'for=192.0.2.1; host = "attacker.example"', 'host="rfc.test"'] as $value) {
            $this->withHeader('Forwarded', $value)
                ->get('http://rfc.test/sign-in')
                ->assertBadRequest();
        }
    }

    public function test_https_session_and_csrf_cookies_have_all_reported_attributes_behind_a_proxy(): void
    {
        config([
            'security.trusted_proxies' => ['10.0.40.81'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.domain' => null,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.40.81'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://rfc.test/sign-in');

        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            $this->assertTrue($cookies->has($name), "Missing {$name}");
            $cookie = $cookies[$name];
            $this->assertTrue($cookie->isSecure(), $name);
            $this->assertTrue($cookie->isHttpOnly(), $name);
            $this->assertSame('lax', $cookie->getSameSite(), $name);
            $this->assertNull($cookie->getDomain(), $name);
        }
    }

    public function test_meta_token_can_authorize_a_post_while_the_csrf_cookie_is_http_only(): void
    {
        // The framework skips CSRF in tests; switch only the environment to exercise it.
        app()->detectEnvironment(fn () => 'production');
        Route::middleware('web')->post('/security-csrf-probe', fn () => response()->noContent());

        $token = str_repeat('a', 40);
        $this->withSession(['_token' => $token])
            ->postJson('/security-csrf-probe')
            ->assertStatus(419);
        $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/security-csrf-probe')
            ->assertNoContent();
    }
}
