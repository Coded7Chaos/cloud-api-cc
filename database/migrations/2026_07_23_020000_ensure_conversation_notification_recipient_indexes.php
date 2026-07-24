<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'conversation_notification_recipients';

    private const UNIQUE_INDEX = 'cnr_conversation_user_unique';

    private const LOOKUP_INDEX = 'cnr_user_conversation_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // Busca por columnas, no sólo por nombre. Así esta migración es segura
        // tanto si producción ya tiene los nombres cortos como si otro motor
        // creó correctamente los índices automáticos de la migración anterior.
        if (! Schema::hasIndex(self::TABLE, ['conversation_id', 'user_id'], 'unique')) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(['conversation_id', 'user_id'], self::UNIQUE_INDEX);
            });
        }

        if (! Schema::hasIndex(self::TABLE, ['user_id', 'conversation_id'])) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index(['user_id', 'conversation_id'], self::LOOKUP_INDEX);
            });
        }
    }

    public function down(): void
    {
        // Deliberadamente no elimina índices: pueden existir desde antes de
        // que esta migración se ejecute (ese es precisamente el estado actual
        // de producción). Quitarlos en un rollback dañaría un esquema válido.
    }
};
