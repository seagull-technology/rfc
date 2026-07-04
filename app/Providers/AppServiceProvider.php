<?php

namespace App\Providers;

use App\Services\NotificationLogService;
use App\Support\AdminSidebarCounters;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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
