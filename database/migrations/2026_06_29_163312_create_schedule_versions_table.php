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
        Schema::create('schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Intervalo de vigencia [effective_from, effective_to)  -- medio abierto
            $table->date('effective_from');           // inclusivo: primer día que aplica
            $table->date('effective_to')->nullable(); // exclusivo: primer día que YA NO aplica
                                                      // null = versión vigente / abierta
            $table->timestamps();

            // Una versión por usuario por fecha de inicio; acelera la búsqueda temporal.
            $table->index(['user_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_versions');
    }
};
