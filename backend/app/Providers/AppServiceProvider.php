<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Limiteurs nommés : chaque route a son propre compteur
        // (un `throttle:5,1` anonyme partagerait le compteur entre toutes les routes d'une même IP).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by('login|'.$request->ip()));
        RateLimiter::for('applications', fn (Request $request) => Limit::perMinute(10)->by('applications|'.$request->ip()));
    }
}
