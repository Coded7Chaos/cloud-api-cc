<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook de la WhatsApp Cloud API. Va en api.php a propósito: el grupo "api"
// no aplica el middleware de CSRF, que bloquearía los POST de Meta.
// URL final: https://TU-DOMINIO/api/whatsapp/webhook
Route::get('/whatsapp/webhook', [WhatsappWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'receive']);
