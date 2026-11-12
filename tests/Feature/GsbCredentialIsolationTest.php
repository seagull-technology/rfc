<?php

namespace Tests\Feature;

use App\Services\Gsb\GsbClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GsbCredentialIsolationTest extends TestCase
{
    public function test_signflow_overrides_do_not_replace_the_other_gateway_credentials(): void
    {
        $this->loadGatewayConfiguration('production-ibm-id', 'production-ibm-secret');

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $client = app(GsbClient::class);
        $client->requestPath('signflow_v2', '/signflow/v2/token');
        $client->requestPath('signflow_v2_open', '/signflow/v2/info/user');
        $client->request('mohe_undergraduate_students');

        foreach (['/signflow/v2/token', '/signflow/v2/info/user'] as $path) {
            Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), $path)
                && $request->hasHeader('X-IBM-Client-Id', 'production-ibm-id')
                && $request->hasHeader('X-IBM-Client-Secret', 'production-ibm-secret')
                && ! $request->hasHeader('X-MODEE-Client-Id')
            );
        }

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/porg-g2g/g2g/api/LastSemester')
            && $request->hasHeader('X-MODEE-Client-Id', 'staging-id')
            && $request->hasHeader('X-MODEE-Client-Secret', 'staging-secret')
            && ! $request->hasHeader('X-IBM-Client-Id')
        );
        Http::assertSentCount(3);
        $this->assertSame('sanad-app-id', config('services.sanad.client_id'));
        $this->assertSame('sanad-app-secret', config('services.sanad.client_secret'));
    }

    public function test_existing_installations_keep_the_shared_credentials_without_overrides(): void
    {
        $this->loadGatewayConfiguration(null, null);

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true])]);

        foreach (['signflow_v2', 'signflow_v2_open'] as $service) {
            app(GsbClient::class)->requestPath($service, '/signflow/v2/'.$service);
        }

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/signflow_v2')
            && $request->hasHeader('X-IBM-Client-Id', 'staging-id')
            && $request->hasHeader('X-IBM-Client-Secret', 'staging-secret')
        );
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/signflow_v2_open')
            && $request->hasHeader('X-IBM-Client-Id', 'staging-id')
            && $request->hasHeader('X-IBM-Client-Secret', 'staging-secret')
        );
    }

    private function loadGatewayConfiguration(?string $id, ?string $secret): void
    {
        $values = [
            'GSB_CLIENT_ID' => 'staging-id',
            'GSB_CLIENT_SECRET' => 'staging-secret',
            'GSB_SIGNFLOW_CLIENT_ID' => $id,
            'GSB_SIGNFLOW_CLIENT_SECRET' => $secret,
            'SANAD_CLIENT_ID' => 'sanad-app-id',
            'SANAD_CLIENT_SECRET' => 'sanad-app-secret',
        ];
        $saved = [];

        foreach ($values as $key => $value) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($value === null ? $key : $key.'='.$value);
            if ($value !== null) {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }

        try {
            config()->set('services', require base_path('config/services.php'));
        } finally {
            foreach ($saved as $key => [$env, $server, $process]) {
                unset($_ENV[$key], $_SERVER[$key]);
                if ($env !== null) {
                    $_ENV[$key] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$key] = $server;
                }
                putenv($process === false ? $key : $key.'='.$process);
            }
        }

        config()->set('services.gsb.enabled', true);
        config()->set('services.gsb.basic_user', '');
        config()->set('services.gsb.basic_pass', '');
        config()->set('services.gsb.bearer', '');
        config()->set('services.gsb.force_ip', '');
        config()->set('services.gsb.send_modee_headers', true);
        config()->set('services.gsb.send_ibm_headers', false);
        config()->set('security.outbound_http.allowed_hosts', ['api-gateway.stg.gsb.gov.jo']);
        config()->set('security.outbound_http.allowed_ports', [443, 9443]);

        foreach (['signflow_v2', 'signflow_v2_open', 'mohe_undergraduate_students'] as $service) {
            config()->set("services.gsb.services.{$service}.enabled", true);
            config()->set("services.gsb.services.{$service}.base_url", 'https://api-gateway.stg.gsb.gov.jo:9443');
        }
        config()->set('services.gsb.services.mohe_undergraduate_students.path', '/porg-g2g/g2g/api/LastSemester');
    }
}
