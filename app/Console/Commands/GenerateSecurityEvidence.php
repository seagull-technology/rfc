<?php

namespace App\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateSecurityEvidence extends Command
{
    protected $signature = 'security:evidence {--label= : Optional release or change-ticket identifier}';

    protected $description = 'Generate a versioned security evidence manifest and dependency inventory';

    public function handle(): int
    {
        $generatedAt = now()->utc();
        $label = Str::slug((string) $this->option('label'));
        $lockHashes = $this->fileHashes([
            'composer.lock',
            'package-lock.json',
            'public/web.config',
            'app/Http/Middleware/AddSecurityHeaders.php',
            'app/Http/Middleware/EnforceTrustedHosts.php',
            'app/Support/DocumentUploadInspector.php',
        ]);
        $fingerprint = hash('sha256', json_encode($lockHashes, JSON_UNESCAPED_SLASHES) ?: '');
        $filename = $generatedAt->format('Ymd-His').($label === '' ? '' : '-'.$label).'.json';
        $payload = [
            'schema' => 'rfc-security-evidence-v1',
            'generated_at_utc' => $generatedAt->toIso8601String(),
            'release' => [
                'label' => $label === '' ? null : $label,
                'fingerprint_sha256' => $fingerprint,
                'app_environment' => app()->environment(),
                'app_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'source_integrity' => $lockHashes,
            'security_configuration' => [
                'trusted_hosts_enforced' => (bool) config('security.trusted_hosts.enforce'),
                'trusted_hosts' => config('security.trusted_hosts.hosts', []),
                'csp_enforced' => (bool) config('security.headers.enabled')
                    && ! (bool) config('security.headers.csp_report_only'),
                'hsts_enabled' => (bool) config('security.headers.hsts'),
                'private_filesystem_serving_disabled' => config('filesystems.disks.local.serve') === false,
                'profile_url_allowlist' => config('security.external_urls.professional_profile_hosts', []),
                'website_url_allowlist' => config('security.external_urls.business_website_hosts', []),
                'map_url_allowlist' => config('security.external_urls.google_maps_hosts', []),
                'outbound_http_allowlist' => config('security.outbound_http.allowed_hosts', []),
                'outbound_http_ports' => config('security.outbound_http.allowed_ports', []),
                'upload_extensions' => config('security.uploads.allowed_extensions', []),
                'upload_max_kilobytes' => config('security.uploads.max_kilobytes'),
            ],
            'route_controls' => $this->routeControls(),
            'template_asset_scan' => $this->templateAssetScan(),
            'sbom' => [
                'composer' => $this->composerPackages(),
                'npm' => $this->npmPackages(),
            ],
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            $this->components->error('Could not encode the security evidence manifest.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $path = 'security-evidence/'.$filename;

        if (! $disk->put($path, $json."\n") || ! $disk->put('security-evidence/latest.json', $json."\n")) {
            $this->components->error('Could not write the security evidence manifest.');

            return self::FAILURE;
        }

        $this->components->info("Security evidence written to the private local disk: {$path}");
        $this->line("Release fingerprint: {$fingerprint}");

        return self::SUCCESS;
    }

    /** @param list<string> $paths
     * @return array<string, string|null>
     */
    private function fileHashes(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            $absolutePath = base_path($path);
            $hashes[$path] = File::isFile($absolutePath) ? hash_file('sha256', $absolutePath) : null;
        }

        return $hashes;
    }

    /** @return array<string, list<string>> */
    private function routeControls(): array
    {
        $routeNames = [
            'login.store',
            'password.otp.send',
            'password.otp.store',
            'password.otp.resend',
            'password.store',
            'otp.store',
            'otp.resend',
            'register.store',
            'register.company.lookup',
            'register.student.lookup',
            'register.organization.lookup',
        ];
        $controls = [];

        foreach ($routeNames as $routeName) {
            $route = RouteFacade::getRoutes()->getByName($routeName);
            $controls[$routeName] = $route instanceof Route
                ? array_values($route->gatherMiddleware())
                : ['MISSING'];
        }

        return $controls;
    }

    /** @return array{forbidden_runtime_urls: list<string>, unnonced_script_or_style_tags: int} */
    private function templateAssetScan(): array
    {
        $forbiddenUrls = [];
        $unnoncedTags = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());
            preg_match_all(
                '#https://(?:cdnjs\.cloudflare\.com|unpkg\.com|fonts\.googleapis\.com|fonts\.gstatic\.com|fonts\.bunny\.net)[^"\'\s<]*#i',
                $contents,
                $matches,
            );
            $forbiddenUrls = [...$forbiddenUrls, ...$matches[0]];
            $unnoncedTags += preg_match_all('/<(?:script|style)\b(?![^>]*\bnonce\s*=)/i', $contents);
        }

        return [
            'forbidden_runtime_urls' => array_values(array_unique($forbiddenUrls)),
            'unnonced_script_or_style_tags' => $unnoncedTags,
        ];
    }

    /** @return list<array{name: string, version: string|null}> */
    private function composerPackages(): array
    {
        $packages = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'version' => InstalledVersions::getPrettyVersion($name),
            ],
            InstalledVersions::getInstalledPackages(),
        );
        usort($packages, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return $packages;
    }

    /** @return list<array{name: string, version: string|null, dev: bool}> */
    private function npmPackages(): array
    {
        $path = base_path('package-lock.json');

        if (! File::isFile($path)) {
            return [];
        }

        $lock = json_decode(File::get($path), true);
        $packages = [];

        foreach (($lock['packages'] ?? []) as $packagePath => $metadata) {
            if ($packagePath === '' || ! is_array($metadata) || ! str_starts_with($packagePath, 'node_modules/')) {
                continue;
            }

            $packages[] = [
                'name' => substr($packagePath, strlen('node_modules/')),
                'version' => isset($metadata['version']) ? (string) $metadata['version'] : null,
                'dev' => (bool) ($metadata['dev'] ?? false),
            ];
        }

        usort($packages, static fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return $packages;
    }
}
