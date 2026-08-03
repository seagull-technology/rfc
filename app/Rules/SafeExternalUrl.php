<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeExternalUrl implements ValidationRule
{
    /**
     * @param  array<int, string>  $allowedHosts
     */
    public function __construct(
        private readonly array $allowedHosts = [],
        private readonly bool $requireAllowlist = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || preg_match('/[\x00-\x20\x7F]/', $value)) {
            $fail(__('validation.safe_external_url'));

            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            $fail(__('validation.safe_external_url'));

            return;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;
        $allowedPorts = array_map('intval', config('security.external_urls.allowed_ports', [443]));

        if (! in_array($port, $allowedPorts, true)
            || filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || $this->isLocalHostname($host)
            || ! $this->isAllowedHost($host)) {
            $fail(__('validation.safe_external_url'));
        }
    }

    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || ! str_contains($host, '.')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.invalid');
    }

    private function isAllowedHost(string $host): bool
    {
        if ($this->allowedHosts === []) {
            return ! $this->requireAllowlist;
        }

        foreach ($this->allowedHosts as $allowedHost) {
            $allowedHost = strtolower(rtrim(trim($allowedHost), '.'));

            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }
}
