<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\LaravelLocalization;
use Tests\TestCase;

class SanadAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv(LaravelLocalization::ENV_ROUTE_KEY.'=en');

        parent::setUp();

        config()->set('services.gsb.enabled', true);

        foreach (['signflow_v2', 'signflow_v2_open'] as $service) {
            config()->set("services.gsb.services.{$service}.enabled", true);
            config()->set("services.gsb.services.{$service}.base_url", 'https://api-gateway.stg.gsb.gov.jo:9443');
            config()->set("services.gsb.services.{$service}.path", '/porg-g2g/g2g/signflow/v2');
            config()->set("services.gsb.services.{$service}.method", 'POST');
            config()->set("services.gsb.services.{$service}.send_modee_headers", false);
            config()->set("services.gsb.services.{$service}.send_ibm_headers", true);
            config()->set("services.gsb.services.{$service}.client_id", 'sanad-client');
            config()->set("services.gsb.services.{$service}.client_secret", 'sanad-secret');
        }

        config()->set('services.sanad.authorization_url', 'https://sanad.example/authorize');
        config()->set('services.sanad.client_id', 'sanad-client');
        config()->set('services.sanad.client_secret', 'sanad-secret');
        config()->set('services.sanad.redirect_uri', 'https://rfc.example/ar/login/sanad/callback');
        config()->set('services.sanad.scope', 'openid');
    }

    public function test_sanad_login_redirect_uses_state_and_s256_pkce(): void
    {
        $response = $this->get(route('sanad.redirect'));

        $response->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $parts = parse_url($location);
        parse_str((string) ($parts['query'] ?? ''), $query);

        $verifier = (string) session('sanad_pkce_verifier');
        $expectedChallenge = rtrim(strtr(
            base64_encode(hash('sha256', $verifier, true)),
            '+/',
            '-_',
        ), '=');

        $this->assertSame('https', $parts['scheme'] ?? null);
        $this->assertSame('sanad.example', $parts['host'] ?? null);
        $this->assertSame('/authorize', $parts['path'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('sanad-client', $query['client_id'] ?? null);
        $this->assertSame('https://rfc.example/ar/login/sanad/callback', $query['redirect_uri'] ?? null);
        $this->assertSame('openid', $query['scope'] ?? null);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertSame($expectedChallenge, $query['code_challenge'] ?? null);
        $this->assertSame(session('sanad_oauth_state'), $query['state'] ?? null);
        $this->assertGreaterThanOrEqual(43, strlen($verifier));
        $this->assertNotEmpty(session('sanad_oauth_started_at'));
    }

    public function test_sanad_login_is_unavailable_without_the_provider_authorization_url(): void
    {
        config()->set('services.sanad.authorization_url', '');

        $response = $this->from(route('login'))->get(route('sanad.redirect'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sanad');
    }

    public function test_sanad_callback_authenticates_an_existing_user_by_national_id(): void
    {
        $user = User::factory()->create([
            'national_id' => '9876543210',
            'status' => 'active',
            'registration_type' => 'company',
            'must_change_password' => false,
        ]);

        Http::fake([
            '*/signflow/v2/token' => Http::response(['access_token' => 'sanad-access-token']),
            '*/signflow/v2/introspect' => Http::response(['nationalId' => '9876543210']),
            '*/signflow/v2/logout' => Http::response(['nationalId' => '9876543210']),
        ]);

        $response = $this
            ->withSession([
                'sanad_oauth_state' => 'expected-state',
                'sanad_pkce_verifier' => 'expected-pkce-verifier',
                'sanad_oauth_started_at' => now()->timestamp,
            ])
            ->get(route('sanad.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/signflow/v2/token')) {
                return false;
            }

            return $request->hasHeader('X-IBM-Client-Id', 'sanad-client')
                && $request->hasHeader('X-IBM-Client-Secret', 'sanad-secret')
                && $request->hasHeader('Accept', 'text/plain')
                && ! $request->hasHeader('X-MODEE-Client-Id')
                && $request['client_id'] === 'sanad-client'
                && $request['client_secret'] === 'sanad-secret'
                && $request['code'] === 'authorization-code'
                && $request['verifier'] === 'expected-pkce-verifier'
                && $request['redirect_uri'] === 'https://rfc.example/ar/login/sanad/callback';
        });

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/signflow/v2/introspect')
            && $request['access_token'] === 'sanad-access-token');
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/signflow/v2/logout')
            && $request['access_token'] === 'sanad-access-token');
    }

    public function test_sanad_callback_rejects_an_invalid_state_without_calling_signflow(): void
    {
        Http::fake();

        $response = $this
            ->withSession([
                'sanad_oauth_state' => 'expected-state',
                'sanad_pkce_verifier' => 'expected-pkce-verifier',
                'sanad_oauth_started_at' => now()->timestamp,
            ])
            ->get(route('sanad.callback', [
                'state' => 'different-state',
                'code' => 'authorization-code',
            ]));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sanad');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_sanad_callback_does_not_create_an_unknown_user(): void
    {
        Http::fake([
            '*/signflow/v2/token' => Http::response(['access_token' => 'sanad-access-token']),
            '*/signflow/v2/introspect' => Http::response(['nationalId' => '1234567890']),
            '*/signflow/v2/logout' => Http::response(['nationalId' => '1234567890']),
        ]);

        $response = $this
            ->withSession([
                'sanad_oauth_state' => 'expected-state',
                'sanad_pkce_verifier' => 'expected-pkce-verifier',
                'sanad_oauth_started_at' => now()->timestamp,
            ])
            ->get(route('sanad.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sanad');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['national_id' => '1234567890']);
    }
}
