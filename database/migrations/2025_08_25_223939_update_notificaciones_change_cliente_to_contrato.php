<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar datos para prevenir errores de FK
        DB::table('notificaciones')->truncate();

        Schema::table('notificaciones', function (Blueprint $table) {
            // 2. Eliminar relación y columna cliente_id
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');

            // 3. Agregar contrato_id como FK
            $table->foreignId('contrato_id')->constrained('contratos');
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
            $table->dropColumn('contrato_id');

            $table->foreignId('cliente_id')->constrained('clientes');
        });
    }
};
