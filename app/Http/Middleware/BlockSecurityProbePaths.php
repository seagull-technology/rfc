<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BlockSecurityProbePaths
{
    /** @var list<string> */
    private array $blockedPrefixes = [
        '_boost',
        '_debugbar',
        'telescope',
        'trace.axd',
        'computemetadata',
        'latest/meta-data',
        'latest/dynamic',
        'metadata',
        'meta-data',
        '.env',
        '.git',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = Str::lower(trim(rawurldecode($request->path()), '/'));

        foreach ($this->blockedPrefixes as $prefix) {
            if ($path === $prefix || Str::startsWith($path, $prefix.'/')) {
                return new Response('Not Found', Response::HTTP_NOT_FOUND, [
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

        return $next($request);
    }
}
