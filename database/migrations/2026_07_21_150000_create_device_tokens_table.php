<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispositivos móviles a los que mandar notificaciones nativas.
     *
     * Es el equivalente de push_subscriptions (navegador / Web Push) para las
     * apps: en vez de endpoint + claves VAPID, guardamos el token que le da al
     * teléfono su transporte del sistema (APNs en iOS, FCM en Android).
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Sanctum. Al cerrar sesión en un teléfono se revoca su token de
            // acceso y, con él, su registro de push: así no le seguimos
            // mandando notificaciones a un aparato deslogueado.
            $table->foreignId('personal_access_token_id')->nullable()
                ->constrained('personal_access_tokens')->nullOnDelete();

            $table->string('platform', 16);          // ios | android
            $table->text('token');                   // token de APNs o de FCM

            // El token puede ser largo y MySQL no indexa TEXT sin prefijo, así
            // que la unicidad va sobre el hash (mismo truco que endpoint_hash
            // en push_subscriptions).
            $table->string('token_hash', 64)->unique();

            $table->string('app_version', 32)->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
