<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ejecuta CREATE TABLE antes de los ALTER de índices. Si una
        // versión anterior falló por el nombre automático demasiado largo, la
        // tabla quedó creada pero la migración no quedó registrada. Completarla
        // permite volver a correr `migrate` sin borrar esa tabla parcial.
        if (Schema::hasTable('conversation_notification_recipients')) {
            Schema::table('conversation_notification_recipients', function (Blueprint $table) {
                $table->unique(['conversation_id', 'user_id'], 'cnr_conversation_user_unique');
                $table->index(['user_id', 'conversation_id'], 'cnr_user_conversation_index');
            });

            return;
        }

        Schema::create('conversation_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Guarda el conjunto de agentes a quienes alguna vez se avisó de
            // este chat hasta que uno lo tome. Así también podemos retirar el
            // aviso de alguien cuyo turno terminó después de recibirlo.
            // Nombres explícitos: los automáticos superan el límite de 64
            // caracteres de MySQL por el nombre largo de esta tabla.
            $table->unique(['conversation_id', 'user_id'], 'cnr_conversation_user_unique');
            $table->index(['user_id', 'conversation_id'], 'cnr_user_conversation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_notification_recipients');
    }
};
