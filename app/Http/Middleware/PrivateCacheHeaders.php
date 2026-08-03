<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldDisableCaching($request)) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        return $response;
    }

    private function shouldDisableCaching(Request $request): bool
    {
        if ($request->user()) {
            return true;
        }

        return $request->routeIs(
            'login*',
            'logout*',
            'password.*',
            'otp.*',
            'register.*',
            'sanad.*',
            'dashboard*',
            'portal.*',
            'admin.*',
            'authority.*',
            'profile.*',
            'applications.*',
            'scouting-requests.*',
            'notifications.*',
        );
    }
}
