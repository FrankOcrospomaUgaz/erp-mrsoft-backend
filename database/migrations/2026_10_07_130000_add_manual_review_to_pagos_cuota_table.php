<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_cuota', function (Blueprint $table) {
            $table->string('metodo_pago', 30)->default('manual')->after('comprobante');
            $table->string('estado_revision', 20)->default('aprobado')->index()->after('metodo_pago');
            $table->text('motivo_rechazo')->nullable()->after('estado_revision');
            $table->foreignId('revisado_por')->nullable()->after('motivo_rechazo')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable()->after('revisado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_cuota', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropColumn(['metodo_pago', 'estado_revision', 'motivo_rechazo', 'revisado_por', 'revisado_at']);
        });
    }
};
