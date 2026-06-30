<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // Idempotencia: el wamid de Meta es único globalmente. Con updateOrCreate
            // sobre esta columna, un webhook reentregado NO crea filas duplicadas.
            $table->string('wa_message_id')->unique();

            $table->string('direction'); // inbound (entrante) | outbound (saliente)
            $table->string('type')->default('text'); // text|image|document|audio|video|sticker|location

            $table->text('body')->nullable(); // texto del mensaje o caption de la media

            // Estado del ciclo de vida (sobre todo para salientes): sent|delivered|read|failed.
            $table->string('status')->nullable();

            // Qué agente lo envió (solo salientes). Nullable para los entrantes.
            $table->foreignId('sender_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Marca de tiempo real del mensaje según Meta (puede diferir de created_at).
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // Cargar un hilo = traer sus mensajes en orden cronológico.
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
