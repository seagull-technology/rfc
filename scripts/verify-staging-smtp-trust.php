<?php

/** Read-only CLI SMTP TLS probe. Never authenticates or sends a message. */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['staging-test', 'app-path:']);
if (! array_key_exists('staging-test', $options) || ! isset($options['app-path'])) {
    fwrite(STDERR, "Usage: php verify-staging-smtp-trust.php --staging-test --app-path=PATH\n");
    exit(1);
}

$report = ['schema' => 'rfc-smtp-trust-v1', 'scope' => 'Read-only SMTP greeting/EHLO/STARTTLS and certificate validation; no authentication or message', 'started_utc' => gmdate('c')];
$socket = null;
$phase = 'preflight';
$safeError = null;
try {
    $root = realpath((string) $options['app-path']);
    if ($root === false || ! is_file($root.'/artisan') || ! is_file($root.'/vendor/autoload.php')) {
        throw new RuntimeException('Application path is invalid.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (rtrim((string) config('app.url'), '/') !== 'https://filmjordan.jo') {
        throw new RuntimeException('Application URL does not match the authorized staging target.');
    }
    $transport = Mail::mailer()->getSymfonyTransport();
    if (! $transport instanceof SmtpTransport || ! $transport->getStream() instanceof SocketStream) {
        throw new RuntimeException('The default transport is not a supported SMTP socket transport.');
    }
    $smtp = $transport->getStream();
    $host = $smtp->getHost();
    $port = $smtp->getPort();
    if ($host === '' || strpbrk($host, "\r\n\0/") !== false || $port < 1 || $port > 65535) {
        throw new RuntimeException('SMTP endpoint configuration is invalid.');
    }
    $report['smtp_port'] = $port;
    $report['implicit_tls'] = $smtp->isTLS();
    $report['smtp_host_is_ip'] = filter_var($host, FILTER_VALIDATE_IP) !== false;
    $report['php_ini'] = php_ini_loaded_file() ?: null;
    $report['php_version'] = PHP_VERSION;
    $report['openssl_version'] = OPENSSL_VERSION_TEXT;
    $tlsOptions = $smtp->getStreamOptions();
    $report['existing_verification_disabled'] = ($tlsOptions['ssl']['verify_peer'] ?? true) === false || ($tlsOptions['ssl']['verify_peer_name'] ?? true) === false;
    foreach (['openssl.cafile', 'openssl.capath'] as $key) {
        $path = (string) ini_get($key);
        $report['trust_locations'][$key] = ['configured' => $path !== '', 'path' => $path ?: null, 'exists' => $path !== '' && file_exists($path), 'readable' => $path !== '' && is_readable($path)];
    }
    foreach (['cafile', 'capath'] as $key) {
        $path = $tlsOptions['ssl'][$key] ?? null;
        $report['trust_locations']['transport.'.$key] = ['configured' => is_string($path) && $path !== '', 'path' => is_string($path) ? $path : null, 'exists' => is_string($path) && file_exists($path), 'readable' => is_string($path) && is_readable($path)];
    }
    $report['openssl_default_locations'] = openssl_get_cert_locations();
    $tlsOptions['ssl'] = array_replace($tlsOptions['ssl'] ?? [], [
        'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
        'peer_name' => $host, 'SNI_enabled' => true,
        'capture_peer_cert' => true, 'capture_peer_cert_chain' => true,
    ]);
    $context = stream_context_create($tlsOptions);
    set_error_handler(static function (int $severity, string $message) use (&$safeError): bool {
        $safeError = match (true) {
            str_contains(strtolower($message), 'certificate verify failed') => 'certificate_verification_failed',
            str_contains(strtolower($message), 'did not match') => 'certificate_hostname_mismatch',
            str_contains(strtolower($message), 'timed out') => 'network_timeout',
            str_contains(strtolower($message), 'refused') => 'connection_refused',
            default => $safeError ?? 'network_or_tls_failure',
        };

        return true;
    });
    try {
        $phase = 'connect';
        $address = (str_contains($host, ':') ? '['.$host.']' : $host).':'.$port;
        $socket = stream_socket_client('tcp://'.$address, $errorNumber, $errorString, 10, STREAM_CLIENT_CONNECT, $context);
        if (! is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed.');
        }
        stream_set_timeout($socket, 10);
        $replyDeadline = microtime(true) + 30;
        $readReply = static function ($connection, int $expected) use ($replyDeadline): void {
            for ($line = 0; $line < 100; $line++) {
                $remaining = $replyDeadline - microtime(true);
                if ($remaining <= 0) {
                    throw new RuntimeException('SMTP reply deadline exceeded.');
                }
                stream_set_timeout($connection, max(1, min(10, (int) ceil($remaining))));
                $reply = fgets($connection, 4096);
                if (! is_string($reply) || ! preg_match('/^(\d{3})([ -])/', $reply, $parts)) {
                    throw new RuntimeException('SMTP reply missing or malformed.');
                }
                if ((int) $parts[1] !== $expected) {
                    throw new RuntimeException('SMTP returned an unexpected reply code.');
                }
                if ($parts[2] === ' ') {
                    return;
                }
            }
            throw new RuntimeException('SMTP reply exceeded the bounded line limit.');
        };
        if (! $smtp->isTLS()) {
            $phase = 'greeting';
            $readReply($socket, 220);
            fwrite($socket, "EHLO security-verification.local\r\n");
            $phase = 'ehlo';
            $readReply($socket, 250);
            fwrite($socket, "STARTTLS\r\n");
            $phase = 'starttls';
            $readReply($socket, 220);
        }
        $phase = 'certificate_validation';
        $report['tls_verified'] = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === true;
        if (! $report['tls_verified']) {
            throw new RuntimeException('SMTP TLS verification failed.');
        }
        $metadata = stream_get_meta_data($socket);
        $report['negotiated_crypto'] = $metadata['crypto'] ?? null;
        $report['status'] = 'tls_verified_no_authentication_or_delivery';
    } finally {
        restore_error_handler();
        if (is_resource($socket)) {
            $peerOptions = stream_context_get_options($socket);
            $chain = $peerOptions['ssl']['peer_certificate_chain'] ?? [];
            if ($chain === [] && isset($peerOptions['ssl']['peer_certificate'])) {
                $chain = [$peerOptions['ssl']['peer_certificate']];
            }
            foreach ($chain as $certificate) {
                $parsed = openssl_x509_parse($certificate);
                $report['peer_certificates'][] = [
                    'sha256' => openssl_x509_fingerprint($certificate, 'sha256'),
                    'subject_common_name' => $parsed['subject']['CN'] ?? null,
                    'issuer_common_name' => $parsed['issuer']['CN'] ?? null,
                    'valid_from_utc' => isset($parsed['validFrom_time_t']) ? gmdate('c', $parsed['validFrom_time_t']) : null,
                    'valid_to_utc' => isset($parsed['validTo_time_t']) ? gmdate('c', $parsed['validTo_time_t']) : null,
                ];
            }
            fclose($socket);
        }
    }
} catch (Throwable $e) {
    $report['status'] = 'failed';
    $report['phase'] = $phase;
    $report['exception_class'] = get_class($e);
    $report['error_category'] = $safeError ?? 'preflight_or_protocol_failure';
    // Exception text may contain credentials from a malformed transport URL.
    $report['raw_exception_redacted'] = true;
}
$report['finished_utc'] = gmdate('c');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
exit(($report['status'] ?? null) === 'tls_verified_no_authentication_or_delivery' && ($report['tls_verified'] ?? false) ? 0 : 2);
