<?php

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
            \App\Http\Middleware\SetPermissionsEntityContext::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SetPermissionsEntityContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
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
