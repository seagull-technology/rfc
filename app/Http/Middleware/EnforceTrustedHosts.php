<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceTrustedHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.trusted_hosts.enforce', false)) {
            return $next($request);
        }

        // getHost() can use X-Forwarded-Host. Check the original authority too.
        $hosts = $request->headers->all('host');

        if (count($hosts) !== 1
            || ! $this->isTrusted($hosts[0])
            || ! $this->isTrusted($request->getHost())
            || ! $this->hasOnlyTrustedForwardedHosts($request)) {
            return $this->rejectedResponse();
        }

        return $next($request);
    }

    private function hasOnlyTrustedForwardedHosts(Request $request): bool
    {
        // Match IIS: only configured X-Forwarded-* headers are supported. Reject
        // the alternate format rather than allow inconsistent parsing by layers.
        if ($request->headers->has('Forwarded')) {
            return false;
        }

        foreach (['X-Forwarded-Host', 'X-Original-Host', 'X-Host'] as $header) {
            foreach ($request->headers->all($header) as $value) {
                foreach (explode(',', $value) as $host) {
                    if (! $this->isTrusted($host)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function isTrusted(string $host): bool
    {
        $host = $this->normalizeHost($host);

        return $host !== '' && in_array($host, $this->trustedHosts(), true);
    }

    /** @return list<string> */
    private function trustedHosts(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): string => $this->normalizeHost((string) $host),
            config('security.trusted_hosts.hosts', []),
        ))));
    }

    private function normalizeHost(string $host): string
    {
        $host = Str::lower(trim($host, " \t\n\r\0\x0B[]\"'"));

        if (substr_count($host, ':') === 1) {
            $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        }

        return rtrim(trim($host, '[]'), '.');
    }

    private function rejectedResponse(): Response
    {
        return new Response('Bad Request', Response::HTTP_BAD_REQUEST, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'",
        ]);
    }
}
