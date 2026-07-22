<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->symbols());

        // Antes la API vivía detrás del grupo "web" y solo la consumía el SPA;
        // ahora está expuesta a las apps móviles, así que necesita techo propio.
        // Por usuario autenticado cuando se puede (dos agentes tras el mismo NAT
        // no se pisan), y por IP cuando todavía no hay sesión ni token.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
