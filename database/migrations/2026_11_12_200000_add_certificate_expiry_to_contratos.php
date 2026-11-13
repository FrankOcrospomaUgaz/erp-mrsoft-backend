<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contratos', 'certificado_fecha_vencimiento')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->date('certificado_fecha_vencimiento')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contratos', 'certificado_fecha_vencimiento')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropColumn('certificado_fecha_vencimiento');
            });
        }
    }
};
