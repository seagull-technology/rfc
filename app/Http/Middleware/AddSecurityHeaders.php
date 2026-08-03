<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->makeNonce();
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $attributeHashes = ['script' => [], 'style' => []];

        if ($this->isHtmlResponse($response)) {
            $content = $response->getContent();

            if (is_string($content)) {
                $attributeHashes = $this->inlineAttributeHashes($content);
            }
        }

        $headers = $response->headers;
        $headers->remove('Server');
        $headers->remove('X-Powered-By');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($this->isAttachment($response)) {
            $headers->set('Content-Security-Policy', "sandbox; default-src 'none'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
            $headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $headers->set('Pragma', 'no-cache');
            $headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

            return $response;
        }

        $cspHeader = config('security.headers.csp_report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $headers->set($cspHeader, $this->contentSecurityPolicy(
            $nonce,
            $attributeHashes,
            $request->isSecure() || app()->isProduction(),
        ));

        if ($request->isSecure() && config('security.headers.hsts', false)) {
            $maxAge = max(0, (int) config('security.headers.hsts_max_age', 31536000));
            $headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        return $response;
    }

    /**
     * @param  array{script: list<string>, style: list<string>}  $attributeHashes
     */
    private function contentSecurityPolicy(
        string $nonce,
        array $attributeHashes,
        bool $upgradeInsecureRequests,
    ): string {
        $nonceSource = "'nonce-{$nonce}'";
        $scriptHashes = $attributeHashes['script'];
        $styleHashes = $attributeHashes['style'];
        $connectSources = app()->environment('local')
            ? "'self' http://localhost:* ws://localhost:* http://127.0.0.1:* ws://127.0.0.1:*"
            : "'self'";

        return implode('; ', array_filter([
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            'script-src '.$this->sourceList(["'self'", $nonceSource, ...($scriptHashes === [] ? [] : ["'unsafe-hashes'", ...$scriptHashes])]),
            "script-src-elem 'self' {$nonceSource}",
            'script-src-attr '.$this->attributeSources($scriptHashes),
            'style-src '.$this->sourceList(["'self'", $nonceSource, ...($styleHashes === [] ? [] : ["'unsafe-hashes'", ...$styleHashes])]),
            "style-src-elem 'self' {$nonceSource}",
            'style-src-attr '.$this->attributeSources($styleHashes),
            "font-src 'self' data:",
            "img-src 'self' data: blob: https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org",
            "connect-src {$connectSources}",
            "frame-src 'self'",
            "media-src 'self' data: blob:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            $upgradeInsecureRequests ? 'upgrade-insecure-requests' : null,
        ]));
    }

    private function makeNonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    private function isHtmlResponse(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        $contentType = Str::lower((string) $response->headers->get('Content-Type'));

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    private function isAttachment(Response $response): bool
    {
        return str_contains(
            Str::lower((string) $response->headers->get('Content-Disposition')),
            'attachment',
        );
    }

    /**
     * @return array{script: list<string>, style: list<string>}
     */
    private function inlineAttributeHashes(string $content): array
    {
        $hashes = ['script' => [], 'style' => []];

        preg_match_all('/\s(on[a-z]+|style)\s*=\s*(["\'])(.*?)\2/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attribute = Str::lower($match[1]);
            $value = trim(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($value === '') {
                continue;
            }

            $type = $attribute === 'style' ? 'style' : 'script';
            $hashes[$type][] = "'sha256-".base64_encode(hash('sha256', $value, true))."'";
        }

        return [
            'script' => array_values(array_unique($hashes['script'])),
            'style' => array_values(array_unique($hashes['style'])),
        ];
    }

    /** @param list<string> $hashes */
    private function attributeSources(array $hashes): string
    {
        return $hashes === []
            ? "'none'"
            : $this->sourceList(["'unsafe-hashes'", ...$hashes]);
    }

    /** @param list<string> $sources */
    private function sourceList(array $sources): string
    {
        return implode(' ', array_values(array_unique(array_filter($sources))));
    }
}
