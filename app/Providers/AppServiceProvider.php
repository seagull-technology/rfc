<?php

namespace App\Providers;

use App\Services\NotificationLogService;
use App\Support\AdminSidebarCounters;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSecurityRateLimiters();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(NotificationSending::class, function (NotificationSending $event): void {
            $this->logNotificationSafely(fn () => app(NotificationLogService::class)
                ->recordSending($event->notifiable, $event->notification, $event->channel));
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            $this->logNotificationSafely(fn () => app(NotificationLogService::class)
                ->recordSent($event->notifiable, $event->notification, $event->channel, $event->response));
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $this->logNotificationSafely(fn () => app(NotificationLogService::class)
                ->recordFailed($event->notifiable, $event->notification, $event->channel, $event->data));
        });

        View::composer('layouts.admin-dashboard', function ($view): void {
            $view->with('layoutSidebarCounters', AdminSidebarCounters::forUser(auth()->user()));
        });
    }

    private function configureSecurityRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(20)->by('login-minute-transport:'.$this->transportAddress($request)),
            Limit::perHour(100)->by('login-transport:'.$this->transportAddress($request)),
            Limit::perMinutes(15, 5)->by('login-identifier:'.$this->hashedInput($request, ['identifier'])),
        ]);

        RateLimiter::for('password-reset', fn (Request $request): array => [
            Limit::perMinute(5)->by('password-reset-ip:'.$request->ip()),
            Limit::perMinute(10)->by('password-reset-minute-transport:'.$this->transportAddress($request)),
            Limit::perHour(20)->by('password-reset-transport:'.$this->transportAddress($request)),
            Limit::perHour(5)->by('password-reset-identifier:'.$this->hashedInput($request, ['identifier'])),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request): array => [
            Limit::perMinute(10)->by('otp-verify-ip:'.$request->ip()),
            Limit::perMinute(20)->by('otp-verify-minute-transport:'.$this->transportAddress($request)),
            Limit::perHour(50)->by('otp-verify-transport:'.$this->transportAddress($request)),
            Limit::perMinutes(10, 5)->by('otp-verify-subject:'.$this->pendingSubject($request)),
        ]);

        RateLimiter::for('otp-resend', fn (Request $request): array => [
            Limit::perHour(10)->by('otp-resend-transport:'.$this->transportAddress($request)),
            Limit::perHour(3)->by('otp-resend-subject:'.$this->pendingSubject($request)),
        ]);

        RateLimiter::for('password-reset-complete', fn (Request $request): array => [
            Limit::perMinute(10)->by('password-reset-complete-ip:'.$request->ip()),
            Limit::perMinute(20)->by('password-reset-complete-minute-transport:'.$this->transportAddress($request)),
            Limit::perHour(30)->by('password-reset-complete-transport:'.$this->transportAddress($request)),
            Limit::perMinutes(15, 5)->by('password-reset-token:'.$this->hashedInput($request, ['token', 'email'])),
        ]);

        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(10)->by('registration-ip:'.$request->ip()),
            Limit::perHour(40)->by('registration-hourly-ip:'.$request->ip()),
            Limit::perMinute(20)->by('registration-transport:'.$this->transportAddress($request)),
            Limit::perHour(100)->by('registration-hourly-transport:'.$this->transportAddress($request)),
        ]);

        RateLimiter::for('registration-lookup', fn (Request $request): array => [
            Limit::perMinute(10)->by('registration-lookup-ip:'.$request->ip()),
            Limit::perHour(40)->by('registration-lookup-hourly-ip:'.$request->ip()),
            Limit::perMinute(20)->by('registration-lookup-transport:'.$this->transportAddress($request)),
            Limit::perHour(100)->by('registration-lookup-hourly-transport:'.$this->transportAddress($request)),
        ]);

        RateLimiter::for('government-lookup', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(10)->by('government-lookup-user:'.$userKey),
                Limit::perHour(60)->by('government-lookup-hourly-user:'.$userKey),
                Limit::perMinute(30)->by('government-lookup-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('content-submission', function (Request $request): array {
            $userKey = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(10)->by('content-submission-user:'.$userKey),
                Limit::perHour(30)->by('content-submission-hourly-user:'.$userKey),
                Limit::perHour(120)->by('content-submission-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('authenticated-write', function (Request $request): Limit|array {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return Limit::none();
            }

            $userKey = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(30)->by('authenticated-write-user:'.$userKey),
                Limit::perMinute(120)->by('authenticated-write-ip:'.$request->ip()),
            ];
        });
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function hashedInput(Request $request, array $fields): string
    {
        foreach ($fields as $field) {
            $value = trim(mb_strtolower((string) $request->input($field, '')));

            if ($value !== '') {
                return hash('sha256', $value);
            }
        }

        return hash('sha256', $request->session()->getId());
    }

    private function pendingSubject(Request $request): string
    {
        $subject = $request->session()->get('pending_auth_user_id')
            ?? $request->session()->get('pending_password_reset_user_id')
            ?? $request->session()->get('pending_password_reset_identifier')
            ?? $request->session()->getId();

        return hash('sha256', (string) $subject);
    }

    private function transportAddress(Request $request): string
    {
        return (string) ($request->server('REMOTE_ADDR') ?: 'unknown');
    }

    private function logNotificationSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Notification audit logging failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
