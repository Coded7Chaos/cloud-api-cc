<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Descarga de media privada de los chats. Firmada (URL temporal) + autenticada.
Route::get('/media/{media}', [MediaController::class, 'show'])
    ->middleware(['auth', 'signed'])
    ->name('media.download');

/*
|--------------------------------------------------------------------------
| API del panel (SPA de mismo origen)
|--------------------------------------------------------------------------
| Estas rutas viven en web.php a propósito: heredan el grupo "web" (sesión +
| cookies + CSRF), así que el SPA se autentica por cookie de sesión, igual que
| el modo SPA de Sanctum pero sin paquete extra. El SPA manda el token XSRF en
| la cabecera X-XSRF-TOKEN. (La WhatsApp webhook sigue en routes/api.php, que es
| stateless y sin CSRF, como necesita Meta.)
*/
Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::apiResource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::put('/users/{user}/schedule', [ScheduleController::class, 'update']);
    });
});

// Shell del SPA: cualquier ruta GET que no sea API/media/health devuelve el
// HTML de React, que se encarga del enrutado del lado del cliente.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|media|up|build|storage).*$');
