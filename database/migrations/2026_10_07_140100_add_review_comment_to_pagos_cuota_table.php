<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_cuota', function (Blueprint $table) {
            $table->text('observacion_revision')->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_cuota', fn (Blueprint $table) => $table->dropColumn('observacion_revision'));
    }
};
