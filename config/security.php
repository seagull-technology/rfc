<?php

$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);
$trustedHosts = array_values(array_filter(array_map(
    static fn (string $host): string => strtolower(trim($host)),
    explode(',', (string) env('TRUSTED_HOSTS', $appHost ?: 'localhost')),
)));
$localExternalUrlHosts = in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'example.com' : '';
$localOutboundUrlHosts = in_array(env('APP_ENV'), ['local', 'testing'], true) ? ',example.com' : '';
$parseHostList = static fn (string $hosts): array => array_values(array_unique(array_filter(array_map(
    static fn (string $host): string => strtolower(rtrim(trim($host), '.')),
    explode(',', $hosts),
))));

return [
    'trusted_hosts' => [
        'enforce' => filter_var(
            env('TRUSTED_HOSTS_ENFORCE', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOL,
        ),
        'hosts' => $trustedHosts,
    ],

    'headers' => [
        'enabled' => filter_var(env('SECURITY_HEADERS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'csp_report_only' => filter_var(env('SECURITY_CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOL),
        'hsts' => filter_var(
            env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOL,
        ),
        'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],

    'external_urls' => [
        'allowed_ports' => [443],
        'google_maps_hosts' => [
            'google.com',
            'maps.google.com',
            'maps.app.goo.gl',
            'goo.gl',
        ],
        'professional_profile_hosts' => $parseHostList((string) env(
            'SECURITY_PROFILE_URL_ALLOWED_HOSTS',
            $localExternalUrlHosts,
        )),
        'business_website_hosts' => $parseHostList((string) env(
            'SECURITY_WEBSITE_URL_ALLOWED_HOSTS',
            $localExternalUrlHosts,
        )),
    ],

    'outbound_http' => [
        'allowed_ports' => [443, 9443],
        'allowed_hosts' => $parseHostList((string) env(
            'SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS',
            'api-gateway.stg.gsb.gov.jo,bulk-sms.gov.jo,signflow.sanad.gov.jo,tawqi3i-signflow.sanad.gov.jo'.$localOutboundUrlHosts,
        )),
    ],

    'uploads' => [
        'max_kilobytes' => 10240,
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png', 'tif', 'tiff'],
    ],
];
