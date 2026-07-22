<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarea_user', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tarea_user', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
