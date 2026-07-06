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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Un hilo por contacto.
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            // Qué agente lo está atendiendo (nullable = sin asignar todavía).
            $table->foreignId('assigned_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status')->default('open'); // open | pending | closed
            $table->timestamp('last_message_at')->nullable(); // para ordenar la bandeja

            $table->timestamps();

            // La bandeja se ordena por actividad y se filtra por agente/estado.
            $table->index(['status', 'last_message_at']);
            $table->index('assigned_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
