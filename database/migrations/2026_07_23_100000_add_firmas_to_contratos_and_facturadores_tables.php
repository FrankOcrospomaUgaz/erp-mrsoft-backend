<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->longText('firma_arrendador')->nullable()->after('estado');
            $table->longText('firma_cliente')->nullable()->after('firma_arrendador');
        });

        Schema::table('facturadores', function (Blueprint $table) {
            $table->longText('firma_arrendador_default')->nullable()->after('porcentaje_igv');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['firma_arrendador', 'firma_cliente']);
        });

        Schema::table('facturadores', function (Blueprint $table) {
            $table->dropColumn('firma_arrendador_default');
        });
    }
};
