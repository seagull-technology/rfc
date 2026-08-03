<?php

namespace App\Console\Commands;

use App\Support\ApprovedOutboundUrl;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class VerifyProductionSecurity extends Command
{
    protected $signature = 'security:production-check';

    protected $description = 'Fail when the release or runtime configuration is unsafe for production';

    public function handle(): int
    {
        $failures = array_values(array_filter([
            app()->environment('production') ? null : 'APP_ENV must be production.',
            config('app.debug') === false ? null : 'APP_DEBUG must be false.',
            str_starts_with((string) config('app.url'), 'https://') ? null : 'APP_URL must use HTTPS.',
            filled(config('app.key')) ? null : 'APP_KEY must be configured.',
            config('services.otp_debug_fallback') === false ? null : 'OTP_DEBUG_FALLBACK must be false.',
            config('session.encrypt') === true ? null : 'SESSION_ENCRYPT must be true.',
            config('session.secure') === true ? null : 'SESSION_SECURE_COOKIE must be true.',
            config('session.http_only') === true ? null : 'SESSION_HTTP_ONLY must be true.',
            in_array(config('session.same_site'), ['lax', 'strict'], true) ? null : 'SESSION_SAME_SITE must be lax or strict.',
            blank(config('session.domain')) ? null : 'SESSION_DOMAIN must be empty/null so the cookie remains host-only.',
            config('filesystems.disks.local.serve') === false ? null : 'FILESYSTEM_LOCAL_SERVE must be false; private files are served by authorized download controllers.',
            config('security.trusted_hosts.enforce') === true ? null : 'TRUSTED_HOSTS_ENFORCE must be true.',
            $this->applicationHostIsTrusted() ? null : 'TRUSTED_HOSTS must contain the APP_URL host.',
            config('security.headers.enabled') === true ? null : 'SECURITY_HEADERS_ENABLED must be true.',
            config('security.headers.csp_report_only') === false ? null : 'SECURITY_CSP_REPORT_ONLY must be false before the security test.',
            config('security.headers.hsts') === true ? null : 'SECURITY_HSTS_ENABLED must be true.',
            InstalledVersions::isInstalled('laravel/boost') ? 'Development dependency laravel/boost is installed. Build releases with composer install --no-dev.' : null,
            $this->hasBrowserLogRoute() ? 'The /_boost/browser-logs route is registered.' : null,
            ...$this->invalidExternalUrlAllowlists(),
            ...$this->unapprovedOutboundDestinations(),
            ...$this->missingRateLimitMiddleware(),
        ]));

        if ($failures !== []) {
            $this->components->error('Production security check failed.');

            foreach ($failures as $failure) {
                $this->line(' - '.$failure);
            }

            return self::FAILURE;
        }

        $this->components->info('Production security checks passed.');

        return self::SUCCESS;
    }

    private function applicationHostIsTrusted(): bool
    {
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $trustedHosts = array_map('strtolower', config('security.trusted_hosts.hosts', []));

        return $appHost !== '' && in_array($appHost, $trustedHosts, true);
    }

    private function hasBrowserLogRoute(): bool
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->contains(fn (Route $route): bool => trim($route->uri(), '/') === '_boost/browser-logs');
    }

    /** @return array<int, string> */
    private function invalidExternalUrlAllowlists(): array
    {
        $required = [
            'security.external_urls.professional_profile_hosts' => 'SECURITY_PROFILE_URL_ALLOWED_HOSTS',
            'security.external_urls.business_website_hosts' => 'SECURITY_WEBSITE_URL_ALLOWED_HOSTS',
            'security.outbound_http.allowed_hosts' => 'SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS',
        ];
        $failures = [];

        foreach ($required as $configKey => $environmentKey) {
            $hosts = config($configKey, []);

            if (! is_array($hosts) || $hosts === []) {
                $failures[] = "{$environmentKey} must contain explicit approved hostnames.";

                continue;
            }

            foreach ($hosts as $host) {
                if (! is_string($host)
                    || filter_var($host, FILTER_VALIDATE_IP)
                    || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $failures[] = "{$environmentKey} contains an invalid hostname.";
                    break;
                }
            }
        }

        return $failures;
    }

    /** @return array<int, string> */
    private function unapprovedOutboundDestinations(): array
    {
        $destinations = [
            'GOV_SMS_BASE' => (string) config('services.gov_sms.base'),
        ];

        if (config('services.gsb.enabled')) {
            foreach (config('services.gsb.services', []) as $service => $serviceConfig) {
                if (! ($serviceConfig['enabled'] ?? false)) {
                    continue;
                }

                $destinations['GSB service '.$service] = (string) ($serviceConfig['base_url']
                    ?? config('services.gsb.base_url'));
            }

            if (config('services.gsb.services.signflow_v2.enabled')) {
                $destinations['SANAD_SIGNFLOW_BASE'] = (string) config('services.sanad.signflow_base');
            }
        }

        if (config('services.gov_company_registry.enabled')) {
            $host = (string) config('services.gov_company_registry.host');
            $port = (int) config('services.gov_company_registry.port', 9443);
            $destinations['GOV_COMPANY_REGISTRY_HOST'] = "https://{$host}:{$port}";
        }

        $approvedOutboundUrl = app(ApprovedOutboundUrl::class);
        $failures = [];

        foreach ($destinations as $name => $url) {
            if (! $approvedOutboundUrl->isAllowed($url)) {
                $failures[] = "{$name} must use an HTTPS host from SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS.";
            }
        }

        return $failures;
    }

    /**
     * @return array<int, string>
     */
    private function missingRateLimitMiddleware(): array
    {
        $required = [
            'login.store' => 'throttle:login',
            'password.otp.send' => 'throttle:password-reset',
            'password.otp.store' => 'throttle:otp-verify',
            'password.otp.resend' => 'throttle:otp-resend',
            'password.store' => 'throttle:password-reset-complete',
            'otp.store' => 'throttle:otp-verify',
            'otp.resend' => 'throttle:otp-resend',
            'register.store' => 'throttle:registration',
        ];

        $failures = [];

        foreach ($required as $routeName => $middleware) {
            $route = RouteFacade::getRoutes()->getByName($routeName);

            if (! $route || ! in_array($middleware, $route->gatherMiddleware(), true)) {
                $failures[] = "Route {$routeName} must use {$middleware}.";
            }
        }

        return $failures;
    }
}
