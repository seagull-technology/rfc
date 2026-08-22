<?php

namespace Tests\Feature;

use App\Rules\SafeExternalUrl;
use App\Rules\SafeExternalUrlOrText;
use App\Support\ApprovedOutboundUrl;
use App\Support\DocumentUploadInspector;
use App\Support\UploadedFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use ZipArchive;

class SecurityHardeningTest extends TestCase
{
    public function test_application_responses_include_enforced_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Content-Security-Policy');

        $this->assertStringContainsString(
            "object-src 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src-elem 'self' 'nonce-[A-Za-z0-9_-]+'/", $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $policy);
        $this->assertStringNotContainsString('unpkg.com', $policy);
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_rendered_login_assets_use_the_response_csp_nonce(): void
    {
        $this->refreshApplicationWithLocale('en');
        $response = $this->get(route('login'));

        $response->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $policy, $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
        $this->assertLessThan(16384, strlen($policy));
    }

    public function test_hsts_is_only_added_to_secure_requests_when_enabled(): void
    {
        config(['security.headers.hsts' => true]);

        $httpResponse = $this->get(route('login'));
        $httpsResponse = $this->get(str_replace('http://', 'https://', route('login')));

        $httpResponse->assertHeaderMissing('Strict-Transport-Security');
        $httpsResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertStringNotContainsString(
            'upgrade-insecure-requests',
            (string) $httpResponse->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'upgrade-insecure-requests',
            (string) $httpsResponse->headers->get('Content-Security-Policy'),
        );
    }

    public function test_authentication_flows_use_the_official_white_rfc_logo(): void
    {
        foreach ([
            'auth/login.blade.php',
            'auth/register.blade.php',
            'auth/verify-otp.blade.php',
            'auth/forgot-password.blade.php',
            'auth/reset-password.blade.php',
            'auth/complete-registration.blade.php',
        ] as $view) {
            $this->assertStringContainsString(
                "asset('images/rfc-logo-white.png')",
                File::get(resource_path('views/'.$view)),
                "Official RFC logo missing from {$view}",
            );
        }

        $this->assertTrue(File::isFile(public_path('images/rfc-logo-white.png')));
    }

    public function test_authentication_pages_only_load_the_runtime_they_need(): void
    {
        $this->refreshApplicationWithLocale('en');

        $loginContent = $this->get(route('login'))->assertOk()->getContent();
        $registerContent = $this->get(route('register'))->assertOk()->getContent();

        foreach ([
            'js/libs.min.js',
            'js/external.min.js',
            'js/dashboard.js',
            'js/widgetcharts.js',
            'js/sidebar.js',
            'js/chart-custom.js',
            'js/select2.js',
            'js/countdown.js',
            'css/all.min.css',
            'css/select2.min.css',
            'fonts/Phosphor-Bold.css',
            'fonts/Phosphor-Fill.css',
            'fonts/Phosphor-Duotone.css',
        ] as $unusedAsset) {
            $this->assertStringNotContainsString($unusedAsset, $loginContent);
            $this->assertStringNotContainsString($unusedAsset, $registerContent);
        }

        $this->assertStringNotContainsString('js/flatpickr.min.js', $loginContent);
        $this->assertStringNotContainsString('css/flatpickr.min.css', $loginContent);
        $this->assertStringContainsString('js/flatpickr.min.js', $registerContent);
        $this->assertStringContainsString('css/flatpickr.min.css', $registerContent);
        $this->assertStringContainsString('images/Clapper.webp', $loginContent);
        $this->assertStringContainsString('images/Clapper.gif', $loginContent);
        $this->assertTrue(File::isFile(public_path('images/Clapper.webp')));
    }

    public function test_untrusted_host_is_rejected_when_enforcement_is_enabled(): void
    {
        config([
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['rfc.test'],
        ]);

        $this->withHeader('Host', 'attacker.example')
            ->get('http://attacker.example/sign-in')
            ->assertBadRequest();

        $this->withHeader('Host', 'rfc.test')
            ->get('http://rfc.test/sign-in')
            ->assertRedirect();
    }

    public function test_untrusted_forwarded_host_headers_are_rejected_before_routing(): void
    {
        config([
            'security.trusted_hosts.enforce' => true,
            'security.trusted_hosts.hosts' => ['rfc.test'],
        ]);

        $this->withHeaders([
            'Host' => 'rfc.test',
            'X-Forwarded-Host' => 'rfc.test',
        ])->get('http://rfc.test/sign-in')->assertRedirect();

        $this->withHeaders([
            'Host' => 'rfc.test',
            'X-Forwarded-Host' => 'attacker.example',
        ])->get('http://rfc.test/sign-in')->assertBadRequest();

        $this->withHeaders([
            'Host' => 'rfc.test',
            'X-Forwarded-Host' => 'rfc.test',
            'Forwarded' => 'for=192.0.2.10;proto=https;host=attacker.example',
        ])->get('http://rfc.test/sign-in')->assertBadRequest();
    }

    public function test_trusted_proxy_setting_survives_configuration_caching(): void
    {
        $bootstrap = File::get(base_path('bootstrap/app.php'));
        $proxyMiddleware = File::get(app_path('Http/Middleware/TrustConfiguredProxies.php'));
        $securityConfig = File::get(config_path('security.php'));

        $this->assertStringContainsString('TrustConfiguredProxies::class', $bootstrap);
        $this->assertStringNotContainsString("env('TRUSTED_PROXIES')", $bootstrap);
        $this->assertStringContainsString("config('security.trusted_proxies', [])", $proxyMiddleware);
        $this->assertStringContainsString("env('TRUSTED_PROXIES', '')", $securityConfig);
        $this->assertArrayHasKey('trusted_proxies', config('security'));
    }

    public function test_windows_deployment_rejects_php_upload_limits_below_registration_requirements(): void
    {
        $deploymentScript = File::get(base_path('deployment/windows/Deploy-RfcRelease.ps1'));
        $deploymentGuide = File::get(base_path('deployment/windows/README.md'));

        $this->assertStringContainsString('Assert-PhpUploadConfiguration', $deploymentScript);
        $this->assertStringContainsString('$uploadBytes -lt 10MB', $deploymentScript);
        $this->assertStringContainsString('$postBytes -lt 16MB', $deploymentScript);
        $this->assertStringContainsString('upload_max_filesize=10M', $deploymentGuide);
        $this->assertStringContainsString('post_max_size=16M', $deploymentGuide);
    }

    public function test_known_security_probe_paths_are_blocked_with_inert_headers(): void
    {
        foreach (['/_boost/browser-logs', '/latest/meta-data', '/computeMetadata/v1', '/.env'] as $path) {
            $this->get($path)
                ->assertNotFound()
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Content-Security-Policy', "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
        }
    }

    public function test_authentication_pages_are_not_cacheable(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_attachment_responses_are_forced_into_a_sandbox(): void
    {
        Route::get('/security-test-attachment', fn () => response('document', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"',
        ]));

        $this->get('/security-test-attachment')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'")
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $this->get('/security-test-attachment')->headers->get('Cache-Control'));
    }

    public function test_external_url_rule_rejects_unsafe_and_internal_destinations(): void
    {
        foreach ([
            'http://example.com/profile',
            'https://localhost/profile',
            'https://127.0.0.1/profile',
            'https://10.10.10.10/profile',
            'https://user:password@example.com/profile',
            'https://example.com:8443/profile',
            'https://internal.test/profile',
            "https://example.com/line\nbreak",
        ] as $url) {
            $validator = Validator::make(['url' => $url], ['url' => [new SafeExternalUrl]]);

            $this->assertTrue($validator->fails(), "Unsafe URL passed validation: {$url}");
        }

        $this->assertFalse(Validator::make(
            ['url' => 'https://profiles.example.com/person?id=10'],
            ['url' => [new SafeExternalUrl]],
        )->fails());
    }

    public function test_google_maps_url_rule_uses_an_explicit_host_allowlist(): void
    {
        $rule = new SafeExternalUrl(config('security.external_urls.google_maps_hosts', []));

        $this->assertFalse(Validator::make(
            ['url' => 'https://maps.google.com/?q=31.9539,35.9106'],
            ['url' => [$rule]],
        )->fails());
        $this->assertTrue(Validator::make(
            ['url' => 'https://maps-google.example.com/phishing'],
            ['url' => [$rule]],
        )->fails());
    }

    public function test_required_external_url_allowlists_fail_closed(): void
    {
        $missingAllowlist = new SafeExternalUrl([], true);
        $approvedProfiles = new SafeExternalUrl(['imdb.com', 'linkedin.com'], true);

        $this->assertTrue(Validator::make(
            ['url' => 'https://example.com/profile'],
            ['url' => [$missingAllowlist]],
        )->fails());
        $this->assertFalse(Validator::make(
            ['url' => 'https://www.imdb.com/name/nm0000001'],
            ['url' => [$approvedProfiles]],
        )->fails());
        $this->assertTrue(Validator::make(
            ['url' => 'https://imdb.example.com/name/nm0000001'],
            ['url' => [$approvedProfiles]],
        )->fails());
    }

    public function test_outbound_http_destinations_are_https_and_explicitly_approved(): void
    {
        config([
            'security.outbound_http.allowed_hosts' => ['api-gateway.stg.gsb.gov.jo'],
            'security.outbound_http.allowed_ports' => [443, 9443],
        ]);
        $rule = app(ApprovedOutboundUrl::class);

        $this->assertTrue($rule->isAllowed('https://api-gateway.stg.gsb.gov.jo:9443/service'));
        $this->assertFalse($rule->isAllowed('http://api-gateway.stg.gsb.gov.jo/service'));
        $this->assertFalse($rule->isAllowed('https://attacker.example/service'));
        $this->assertFalse($rule->isAllowed('https://127.0.0.1/service'));
        $this->assertFalse($rule->isAllowed('https://user:secret@api-gateway.stg.gsb.gov.jo/service'));
    }

    public function test_location_address_rule_allows_text_but_hardens_pasted_urls(): void
    {
        $rule = new SafeExternalUrlOrText;

        $this->assertFalse(Validator::make(
            ['address' => 'King Hussein Business Park, Amman'],
            ['address' => [$rule]],
        )->fails());
        $this->assertFalse(Validator::make(
            ['address' => 'https://maps.google.com/?q=31.9539,35.9106'],
            ['address' => [$rule]],
        )->fails());

        foreach (['http://example.com/map', 'javascript:alert(1)', '//example.com/map'] as $address) {
            $this->assertTrue(Validator::make(
                ['address' => $address],
                ['address' => [$rule]],
            )->fails(), "Unsafe location address passed validation: {$address}");
        }
    }

    public function test_pdf_inspector_accepts_a_plain_pdf_and_rejects_active_content(): void
    {
        $inspector = app(DocumentUploadInspector::class);
        $safePdf = UploadedFile::fake()->createWithContent(
            'safe.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );
        $activePdf = UploadedFile::fake()->createWithContent(
            'active.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction 2 0 R >>\nendobj\n2 0 obj\n<< /S /JavaScript /JS (alert(1)) >>\nendobj\n%%EOF",
        );

        $this->assertNull($inspector->inspect($safePdf));
        $this->assertSame('active_content', $inspector->inspect($activePdf));
    }

    public function test_pdf_inspector_accepts_safe_links_view_destinations_and_stream_bytes(): void
    {
        $safePdf = UploadedFile::fake()->createWithContent(
            'safe-linked.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction [3 0 R /FitH null] >>\nendobj\n"
            ."2 0 obj\n<< /Subtype /Link /A << /S /URI /URI (https://example.com) >> >>\nendobj\n"
            ."3 0 obj\n<< /Length 18 >>\nstream\nordinary /jS bytes\nendstream\nendobj\n%%EOF",
        );

        $this->assertNull(app(DocumentUploadInspector::class)->inspect($safePdf));
    }

    public function test_upload_inspector_uses_the_windows_pathname_when_realpath_is_unavailable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rfc-upload-');

        $this->assertIsString($path);
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        $file = new class($path, 'company-document.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        try {
            $this->assertNull(app(DocumentUploadInspector::class)->inspect($file));
        } finally {
            @unlink($path);
        }
    }

    public function test_uploaded_file_storage_uses_the_windows_pathname_when_realpath_is_unavailable(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'rfc-upload-');

        $this->assertIsString($path);
        $contents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
        file_put_contents($path, $contents);

        $file = new class($path, 'company-document.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        try {
            $storedPath = UploadedFileStorage::store($file, 'registration-documents/company');

            $this->assertStringStartsWith('registration-documents/company/', $storedPath);
            Storage::disk('local')->assertExists($storedPath);
            $this->assertSame($contents, Storage::disk('local')->get($storedPath));
        } finally {
            @unlink($path);
        }
    }

    public function test_pdf_inspector_rejects_active_content_hidden_in_a_compressed_stream(): void
    {
        $payload = gzcompress('2 0 << /S /JavaScript /JS (app.alertMsg("unsafe")) >>');
        $pdf = UploadedFile::fake()->createWithContent(
            'compressed-active.pdf',
            "%PDF-1.7\n1 0 obj\n<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ".strlen($payload)." >>\nstream\n{$payload}\nendstream\nendobj\n%%EOF",
        );

        $this->assertSame('active_content', app(DocumentUploadInspector::class)->inspect($pdf));
    }

    public function test_office_archives_reject_external_relationships(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rfc-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $zip->addFromString('word/_rels/document.xml.rels', '<Relationships><Relationship Target="https://attacker.example/template" TargetMode="External"/></Relationships>');
        $zip->close();

        try {
            $file = new UploadedFile(
                $path,
                'external-template.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true,
            );

            $this->assertSame('active_content', app(DocumentUploadInspector::class)->inspect($file));
        } finally {
            @unlink($path);
        }
    }

    public function test_csv_inspector_rejects_spreadsheet_formula_payloads(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'formula.csv',
            "name,value\nunsafe,=HYPERLINK(\"https://attacker.example\")",
        );

        $this->assertSame('active_content', app(DocumentUploadInspector::class)->inspect($csv));
    }

    public function test_pdf_inspector_rejects_extension_and_signature_mismatches(): void
    {
        $inspector = app(DocumentUploadInspector::class);
        $fakePdf = UploadedFile::fake()->createWithContent('fake.pdf', 'This is not a PDF.');
        $executable = UploadedFile::fake()->createWithContent('payload.exe', 'MZ executable');

        $this->assertNotNull($inspector->inspect($fakePdf));
        $this->assertSame('invalid_extension', $inspector->inspect($executable));
    }

    public function test_upload_inspector_accepts_existing_tiff_identity_attachments(): void
    {
        $inspector = app(DocumentUploadInspector::class);
        $tiff = UploadedFile::fake()->createWithContent(
            'identity.tiff',
            "II\x2A\x00\x08\x00\x00\x00\x00\x00\x00\x00",
        );

        $this->assertNull($inspector->inspect($tiff));
    }
}
