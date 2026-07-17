<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RoleController;
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

    // Recuperación de contraseña ("olvidé mi contraseña"). Throttle propio
    // aparte del que ya trae el password broker (config/auth.php), para
    // frenar spam de envíos antes de tocar la tabla password_reset_tokens.
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1');

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);
        Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);

        // Catálogo de roles de solo lectura, para el selector del form de Usuarios.
        Route::get('/roles', [RoleController::class, 'index']);

        Route::apiResource('users', UserController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor('index', 'permission:usuarios.ver')
            ->middlewareFor('store', 'permission:usuarios.crear')
            ->middlewareFor('update', 'permission:usuarios.editar')
            ->middlewareFor('destroy', 'permission:usuarios.eliminar');

        Route::get('/conversations', [ConversationController::class, 'index'])
            ->middleware('permission:conversaciones.ver');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
            ->middleware('permission:conversaciones.ver');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware('permission:conversaciones.responder');

        Route::get('/schedules', [ScheduleController::class, 'index'])
            ->middleware('permission:horarios.ver');
        Route::put('/users/{user}/schedule', [ScheduleController::class, 'update'])
            ->middleware('permission:horarios.editar');
    });
});

// Shell del SPA: cualquier ruta GET que no sea API/media/health devuelve el
// HTML de React, que se encarga del enrutado del lado del cliente.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|media|up|build|storage).*$');
