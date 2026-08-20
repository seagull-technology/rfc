<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsProxyUrlGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_https_app_url_forces_secure_generated_routes(): void
    {
        config()->set('app.url', 'https://filmjordan.jo');
        app()->setLocale('ar');

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame(
            'https://rfc.test/sign-in',
            route('login'),
        );

        $this->assertSame(
            'https://rfc.test/register',
            route('register'),
        );
    }

    public function test_trusted_reverse_proxy_keeps_validation_redirect_on_https(): void
    {
        config([
            'app.url' => 'http://rfc.test',
            'security.trusted_proxies' => ['10.0.40.81'],
        ]);

        Route::get('/proxy-scheme-probe', fn (Request $request) => response()->json([
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
        ]));

        $serverVariables = ['REMOTE_ADDR' => '10.0.40.81'];
        $forwardedHeaders = ['X-Forwarded-Proto' => 'https'];

        $this->withServerVariables($serverVariables)
            ->withHeaders($forwardedHeaders)
            ->get('http://rfc.test/proxy-scheme-probe')
            ->assertOk()
            ->assertJson([
                'remote_addr' => '10.0.40.81',
                'secure' => true,
                'scheme' => 'https',
            ]);

        $this->withServerVariables($serverVariables)
            ->withHeaders($forwardedHeaders)
            ->post('http://rfc.test/register', [
                'registration_type' => 'company',
            ])->assertRedirect('https://rfc.test');
    }
}
