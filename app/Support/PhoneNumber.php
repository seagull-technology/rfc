<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(string $phone, string $defaultCountryCode = '962'): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $digits = ltrim((string) $digits, '0');

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, $defaultCountryCode)) {
            return $digits;
        }

        if (str_starts_with($digits, '7')) {
            return $defaultCountryCode.$digits;
        }

        return $digits;
    }
}
