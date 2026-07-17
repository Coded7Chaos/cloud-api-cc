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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Identidad del contacto en WhatsApp.
            $table->string('wa_id')->unique();        // su número (ej. 5215512345678)
            $table->string('profile_name')->nullable(); // nombre de perfil que envía Meta
            $table->string('phone')->nullable();        // número formateado, si lo querés aparte

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
