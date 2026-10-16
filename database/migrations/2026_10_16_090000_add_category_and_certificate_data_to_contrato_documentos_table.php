<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrato_documentos', function (Blueprint $table) {
            $table->string('categoria', 40)->default('alta_servicio')->after('contrato_id');
            $table->text('pin')->nullable()->after('categoria');
            $table->date('fecha_vencimiento')->nullable()->after('pin');
            $table->index(['contrato_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::table('contrato_documentos', function (Blueprint $table) {
            $table->dropIndex(['contrato_id', 'categoria']);
            $table->dropColumn(['categoria', 'pin', 'fecha_vencimiento']);
        });
    }
};
