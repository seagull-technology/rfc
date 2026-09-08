<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as FrameworkStartSession;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StartSession extends FrameworkStartSession
{
    private const NAVIGATION_RESPONSE = '_rfc_navigation_response';

    public function __construct(SessionManager $manager, CacheFactory $cache)
    {
        // Preserve the framework's cache resolver for routes using session locks.
        parent::__construct($manager, fn (): CacheFactory => $cache);
    }

    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        return parent::handleStatefulRequest($request, $session, function (Request $request) use ($next): Response {
            $response = $next($request);
            $request->attributes->set(self::NAVIGATION_RESPONSE, $this->isNavigationResponse($request, $response));

            return $response;
        });
    }

    protected function storeCurrentUrl(Request $request, $session)
    {
        if ($request->attributes->get(self::NAVIGATION_RESPONSE) === true) {
            parent::storeCurrentUrl($request, $session);
        }
    }

    protected function saveSession($request)
    {
        if ($request->isMethodSafe()
            && ! $request->isPrecognitive()
            && $request->attributes->get(self::NAVIGATION_RESPONSE) !== true) {
            // Images, downloads and polling must not consume errors/old input
            // intended for the next page rendered after a validation redirect.
            $request->session()->reflash();
        }

        parent::saveSession($request);
    }

    private function isNavigationResponse(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')
            || $request->ajax()
            || $request->expectsJson()
            || $request->prefetch()
            || $request->isPrecognitive()
            || ! $response->isSuccessful()
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || $response->headers->has('Content-Disposition')) {
            return false;
        }

        $destination = strtolower((string) $request->header('Sec-Fetch-Dest', ''));

        if ($destination !== '' && $destination !== 'document') {
            return false;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));

        return in_array($contentType, ['text/html', 'application/xhtml+xml'], true);
    }
}
