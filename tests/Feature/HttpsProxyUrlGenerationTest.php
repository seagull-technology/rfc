<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
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
}
