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
        Schema::create('schedule_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_version_id')->constrained()->cascadeOnDelete();

            // Patrón semanal (ISO-8601): 1=Lunes ... 6=Sábado, 7=Domingo.
            // Por defecto no se crean filas para el domingo.
            $table->unsignedTinyInteger('weekday');

            $table->time('start_time'); // p.ej. 07:00
            $table->time('end_time');   // p.ej. 22:00

            $table->timestamps();

            // Permite varios turnos por día (mañana/tarde con descanso) y consulta rápida.
            $table->index(['schedule_version_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_shifts');
    }
};
