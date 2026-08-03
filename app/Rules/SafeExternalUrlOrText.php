<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeExternalUrlOrText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        if (! str_starts_with($trimmed, '//')
            && ! preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed)) {
            return;
        }

        (new SafeExternalUrl(
            config('security.external_urls.google_maps_hosts', []),
            true,
        ))->validate($attribute, $trimmed, $fail);
    }
}
