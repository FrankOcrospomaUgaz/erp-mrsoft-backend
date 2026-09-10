<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->string('zip_path')->nullable()->after('cdr_path');
            $table->unique('cuota_id', 'comprobantes_cuota_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropUnique('comprobantes_cuota_id_unique');
            $table->dropColumn('zip_path');
        });
    }
};
