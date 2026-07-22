<?php

use App\Http\Middleware\EnsurePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Navegación web sin sesión -> al login del SPA. Las rutas /api/* que
        // esperan JSON devuelven 401 igual (no redirigen), gracias a la regla
        // shouldRenderJsonWhen de abajo.
        $middleware->redirectGuestsTo(fn () => '/login');

        // Un solo juego de rutas sirve a dos clientes con el guard sanctum:
        //  - SPA del panel (mismo origen): statefulApi() antepone cookies +
        //    sesión + CSRF a /api/*, así que sigue autenticando por cookie
        //    exactamente igual que antes de exponer la API.
        //  - Apps móviles (otro origen): sin cookie de sesión, sanctum cae al
        //    token Bearer de personal_access_tokens.
        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
