<?php

namespace App\Providers;

use App\Support\AdminSidebarCounters;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.admin-dashboard', function ($view): void {
            $view->with('layoutSidebarCounters', AdminSidebarCounters::forUser(auth()->user()));
        });
    }
}
