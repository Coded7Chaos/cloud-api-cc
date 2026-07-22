<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook de la WhatsApp Cloud API. Va en api.php a propósito: el grupo "api"
// no aplica el middleware de CSRF, que bloquearía los POST de Meta.
// URL final: https://TU-DOMINIO/api/whatsapp/webhook
//
// Sin throttle: los picos de eventos de Meta son legítimos y un 429 solo
// lograría que reintente el mismo evento una y otra vez.
Route::withoutMiddleware('throttle:api')->group(function () {
    Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify']);
    Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'receive']);
});

/*
|--------------------------------------------------------------------------
| API del panel
|--------------------------------------------------------------------------
| El mismo juego de rutas (routes/panel.php) se monta dos veces:
|
|   /api/*     Superficie sin versión. Es la que ya consumen el SPA React
|              (baseURL '/api') y la app Flutter; se mantiene para no romper
|              a los clientes existentes.
|   /api/v1/*  Superficie canónica y versionada para las apps móviles. Un
|              futuro /api/v2 podrá cambiar el contrato sin dejar tiradas a
|              las versiones de la app ya instaladas en los teléfonos.
|
| Los nombres de ruta del grupo versionado llevan el prefijo "v1." para no
| chocar con los del grupo sin versión.
*/
$panel = require __DIR__.'/panel.php';

Route::group([], $panel);
Route::prefix('v1')->name('v1.')->group($panel);
