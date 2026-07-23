<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompletarTareaController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TareaController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del panel (SPA web + apps móviles)
|--------------------------------------------------------------------------
| Este archivo devuelve un closure en vez de registrar rutas directamente,
| porque routes/api.php lo monta DOS veces: sin versión (/api/*, que es lo
| que ya consumen el SPA React y la app Flutter) y bajo /api/v1/*, que es la
| superficie canónica para móvil. Así un futuro v2 puede romper el contrato
| sin dejar tiradas a las apps ya instaladas.
|
| Un solo juego de rutas sirve a los dos clientes gracias al guard sanctum:
| el SPA es de mismo origen y autentica por cookie de sesión (statefulApi()
| en bootstrap/app.php le antepone sesión + CSRF), y el móvil manda el token
| Bearer. Ninguno de los dos necesita rutas propias.
*/

return function (): void {
    // Throttle propio y agresivo en login: es el único endpoint público que
    // prueba credenciales, y ahora está expuesto a internet, no solo al SPA.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Recuperación de contraseña ("olvidé mi contraseña"). Throttle propio
    // aparte del que ya trae el password broker (config/auth.php), para
    // frenar spam de envíos antes de tocar la tabla password_reset_tokens.
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1');
    Route::get('/invitations/status', [InvitationController::class, 'status'])
        ->middleware('throttle:12,1');
    Route::post('/invitations/accept', [InvitationController::class, 'accept'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', DashboardController::class);

        // Perfil propio: cada usuario edita su cuenta. Sin permiso, solo estar
        // logueado; siempre opera sobre el usuario autenticado.
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar']);

        // Notificaciones del navegador (Web Push / VAPID).
        Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);
        Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store']);
        Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy']);

        // Notificaciones nativas: el móvil registra acá su token de APNs/FCM.
        Route::get('/devices', [DeviceTokenController::class, 'index']);
        Route::post('/devices', [DeviceTokenController::class, 'store']);
        Route::delete('/devices', [DeviceTokenController::class, 'destroy']);

        // Roles y permisos. El index alimenta tanto la pestaña de gestión como
        // el selector de rol del alta de usuarios; por eso pide roles.ver, que
        // el administrador ya tiene. La gestión (alta/edición/baja) va con su
        // permiso propio.
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:roles.ver');
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.ver');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.crear');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.editar');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.eliminar');

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
        // Historial paginado hacia atrás: el detalle solo trae la última página
        // y el móvil pide las anteriores con ?before=<id> al scrollear.
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])
            ->middleware('permission:conversaciones.ver');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware('permission:conversaciones.responder');

        // Adjuntos de los chats. URL estable (no firmada) para que el caché de
        // imágenes del móvil sirva de algo; la autorización va por conversación.
        Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');

        Route::get('/schedules', [ScheduleController::class, 'index'])
            ->middleware('permission:horarios.ver');
        Route::put('/users/{user}/schedule', [ScheduleController::class, 'update'])
            ->middleware('permission:horarios.editar');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:auditoria.ver');

        Route::get('/tareas', [TareaController::class, 'index'])
            ->middleware('permission:tareas.ver');
        Route::post('/tareas', [TareaController::class, 'store'])
            ->middleware('permission:tareas.crear');
        Route::patch('/tareas/{tarea}/completar', CompletarTareaController::class)
            ->middleware('permission:tareas.completar');
    });
};
