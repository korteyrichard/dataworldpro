<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Alert;

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
        View::composer(['Dashboard/*', 'test-alert'], function ($view) {
            $activeAlert = Alert::where('is_active', true)->latest()->first();
            $view->with('activeAlert', $activeAlert);
        });
    }
}
