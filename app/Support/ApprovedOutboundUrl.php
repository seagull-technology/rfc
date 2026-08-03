<?php

namespace App\Support;

use InvalidArgumentException;

class ApprovedOutboundUrl
{
    public function isAllowed(string $url): bool
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;

        if (filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || ! in_array($port, config('security.outbound_http.allowed_ports', [443]), true)) {
            return false;
        }

        foreach (config('security.outbound_http.allowed_hosts', []) as $allowedHost) {
            $allowedHost = strtolower(rtrim(trim((string) $allowedHost), '.'));

            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    public function ensureAllowed(string $url): void
    {
        if (! $this->isAllowed($url)) {
            throw new InvalidArgumentException('Outbound HTTP destination is not approved.');
        }
    }
}
