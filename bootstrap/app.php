<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\BlockSecurityProbePaths;
use App\Http\Middleware\EnforceTrustedHosts;
use App\Http\Middleware\PrivateCacheHeaders;
use App\Http\Middleware\SetPermissionsEntityContext;
use App\Http\Middleware\ValidateUploadedFiles;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            EnforceTrustedHosts::class,
            BlockSecurityProbePaths::class,
            AddSecurityHeaders::class,
            PrivateCacheHeaders::class,
            ValidateUploadedFiles::class,
        ]);

        $trustedProxies = trim((string) env('TRUSTED_PROXIES'));

        if ($trustedProxies !== '') {
            $middleware->trustProxies(
                at: in_array($trustedProxies, ['*', '**'], true)
                    ? $trustedProxies
                    : array_map('trim', explode(',', $trustedProxies)),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->alias([
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeCookieRedirect' => LocaleCookieRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            SetPermissionsEntityContext::class,
        ]);

        $middleware->api(append: [
            SetPermissionsEntityContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status < 400) {
                return $response;
            }

            $view = match (true) {
                View::exists("errors.{$status}") => "errors.{$status}",
                $status >= 400 && $status < 500 && View::exists('errors.4xx') => 'errors.4xx',
                $status >= 500 && View::exists('errors.5xx') => 'errors.5xx',
                default => null,
            };

            if ($view === null) {
                return $response;
            }

            return response()->view(
                $view,
                ['exception' => $exception],
                $status,
                $response->headers->all(),
            );
        });
    })->create();
