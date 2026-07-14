<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->enum('estado', ['activo', 'anulado'])->default('activo')->after('forma_pago');
            $table->enum('periodicidad_cuota', ['mensual', 'semestral', 'anual'])->nullable()->after('estado');
            $table->text('motivo_anulacion')->nullable()->after('periodicidad_cuota');
            $table->date('fecha_anulacion')->nullable()->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'estado',
                'periodicidad_cuota',
                'motivo_anulacion',
                'fecha_anulacion',
            ]);
        });
    }
};
