<?php


namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // general limit for every API route: 120 requests / minute per user (or IP)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // login / register: 10 tries per minute per (email + IP)
        RateLimiter::for('login', function (Request $request) {
            $who = strtolower((string) $request->input('identifier', $request->input('email', '')));

            return Limit::perMinute(10)->by($who.'|'.$request->ip());
        });
    }
}
