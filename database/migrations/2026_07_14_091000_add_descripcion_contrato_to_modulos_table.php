<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            if (!Schema::hasColumn('modulos', 'descripcion_contrato')) {
                $table->text('descripcion_contrato')->nullable()->after('nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            if (Schema::hasColumn('modulos', 'descripcion_contrato')) {
                $table->dropColumn('descripcion_contrato');
            }
        });
    }
};
