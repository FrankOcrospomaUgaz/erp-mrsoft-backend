<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'tipo')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->string('tipo', 20)->nullable()->after('nombre');
            });
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE productos SET tipo = 'servicio' WHERE tipo IS NULL OR tipo = ''");
        } else {
            DB::statement("UPDATE productos SET tipo = 'servicio' WHERE tipo IS NULL OR tipo = ''");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'tipo')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }
};
