<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as FrameworkValidateCsrfToken;

class ValidateCsrfToken extends FrameworkValidateCsrfToken
{
    /**
     * Keep the framework CSRF cookie compatible with the application's security policy.
     *
     * Application JavaScript reads the CSRF token from the page's nonce-protected meta
     * element, so the cookie does not need to be accessible to client-side scripts.
     */
    protected function newCookie($request, $config)
    {
        return parent::newCookie($request, $config)->withHttpOnly();
    }
}
