<?php

use App\Http\Controllers\Api\AdminSeguimientoController;
use App\Http\Controllers\Api\AgenteHistorialController;
use App\Http\Controllers\Api\AgenteResumenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompletarTareaController;
use App\Http\Controllers\Api\ConversationController as ApiConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TareaController as ApiTareaController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Web\AdminScheduleController;
use App\Http\Controllers\Web\AdminTaskController;
use App\Http\Controllers\Web\AdminTrackingController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\AgentController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\SessionController;
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

        Route::get('/conversations', [ApiConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ApiConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::put('/users/{user}/schedule', [ScheduleController::class, 'update']);
        Route::post('/tareas', [ApiTareaController::class, 'store']);
        Route::get('/agente/resumen', AgenteResumenController::class);
        Route::get('/agente/historial', AgenteHistorialController::class);
        Route::patch('/agente/tareas/{tarea}/completar', CompletarTareaController::class);
        Route::get('/admin/seguimiento', AdminSeguimientoController::class);
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::redirect('/', '/chats');
    Route::get('/chats/{conversation?}', [ChatController::class, 'index'])->name('chats.index');

    Route::middleware('agent')->prefix('agente')->name('agent.')->group(function () {
        Route::get('/', [AgentController::class, 'dashboard'])->name('dashboard');
        Route::get('/historial', [AgentController::class, 'history'])->name('history');
        Route::patch('/tareas/{tarea}/completar', [AgentController::class, 'completeTask'])->name('tasks.complete');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('usuarios', AdminUserController::class)->parameters(['usuarios' => 'user'])->except(['show']);
        Route::get('/horarios', [AdminScheduleController::class, 'index'])->name('schedules.index');
        Route::put('/horarios/{user}', [AdminScheduleController::class, 'update'])->name('schedules.update');
        Route::get('/tareas/create', [AdminTaskController::class, 'create'])->name('tasks.create');
        Route::post('/tareas', [AdminTaskController::class, 'store'])->name('tasks.store');
        Route::get('/seguimiento', AdminTrackingController::class)->name('tracking');
    });
});
