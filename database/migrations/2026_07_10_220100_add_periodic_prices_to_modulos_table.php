<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->decimal('precio_mensual', 10, 2)->nullable()->after('precio_unitario');
            $table->decimal('precio_anual', 10, 2)->nullable()->after('precio_mensual');
        });

        DB::table('modulos')->update([
            'precio_mensual' => DB::raw('precio_unitario'),
            'precio_anual' => DB::raw('precio_unitario'),
        ]);
    }

    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->dropColumn(['precio_mensual', 'precio_anual']);
        });
    }
};
