<?php

namespace App\Support;

final class SmtpUrl
{
    public static function normalizeKnownSchemes(string $url): string
    {
        // Never rebuild the URL: credentials and unrelated query bytes are preserved.
        $url = preg_replace('/\Asmtp:\/\//i', 'smtp://', $url);
        $parameters = self::schemeParameters($url);

        if (count($parameters) === 1 && $parameters[0]['scalar']) {
            $parameter = $parameters[0];
            $decoded = urldecode($parameter['value']);
            $scheme = strtolower($decoded);
            if ($decoded !== $scheme && in_array($scheme, ['smtp', 'smtps'], true)) {
                $url = substr_replace($url, $scheme, $parameter['offset'], strlen($parameter['value']));
            }
        }

        return $url;
    }

    public static function hasAmbiguousScheme(string $url): bool
    {
        $parameters = self::schemeParameters($url);

        return count($parameters) > 1 || (count($parameters) === 1 && ! $parameters[0]['scalar']);
    }

    /** @return array<int, array{value: string, offset: int, scalar: bool}> */
    private static function schemeParameters(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return [];
        }

        $parameters = [];
        $separators = preg_quote(ini_get('arg_separator.input') ?: '&', '/');
        foreach (preg_split('/['.$separators.']/', $query, flags: PREG_SPLIT_OFFSET_CAPTURE) as [$part, $offset]) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            // Match parse_str's decoded keys and array syntax, as used by Laravel.
            // Checking each occurrence also detects aliases hidden by last-value wins.
            parse_str($key.'=1', $probe);
            if (array_key_exists('scheme', $probe)) {
                $parameters[] = [
                    'value' => $value,
                    'offset' => strpos($url, '?') + 1 + $offset + strlen($key) + 1,
                    'scalar' => urldecode($key) === 'scheme' && is_string($probe['scheme']) && str_contains($part, '='),
                ];
            }
        }

        return $parameters;
    }
}
