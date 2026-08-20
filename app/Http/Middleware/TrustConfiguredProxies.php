<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustConfiguredProxies extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    /**
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        $proxies = array_values(array_filter(
            (array) config('security.trusted_proxies', []),
            static fn (mixed $proxy): bool => is_string($proxy) && trim($proxy) !== '',
        ));

        if ($proxies === []) {
            return null;
        }

        return count($proxies) === 1 && in_array($proxies[0], ['*', '**'], true)
            ? $proxies[0]
            : $proxies;
    }
}
