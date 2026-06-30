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
        Schema::create('message_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();

            // SOLO la referencia al archivo. El binario vive en el storage, no acá.
            $table->string('disk')->default('local');   // disco de Laravel (local = privado)
            $table->string('storage_path');              // ruta dentro del disco
            $table->string('mime_type')->nullable();     // image/jpeg, application/pdf, ...
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->string('sha256', 64)->nullable();    // para deduplicar / verificar integridad

            // El id de media de Meta, por si necesitás volver a pedir la URL temporal.
            $table->string('wa_media_id')->nullable();

            $table->timestamps();

            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_media');
    }
};
