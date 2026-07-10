<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'direccion')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('direccion')->nullable()->after('nombre_comercial');
            });
        }

        if (!Schema::hasColumn('contactos_cliente', 'dni')) {
            Schema::table('contactos_cliente', function (Blueprint $table) {
                $table->string('dni', 20)->nullable()->after('cliente_id');
            });
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE contactos_cliente MODIFY celular VARCHAR(255) NULL');
            DB::statement('ALTER TABLE contactos_cliente MODIFY email VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE contactos_cliente ALTER COLUMN celular DROP NOT NULL');
            DB::statement('ALTER TABLE contactos_cliente ALTER COLUMN email DROP NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE contactos_cliente MODIFY celular VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE contactos_cliente MODIFY email VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE contactos_cliente ALTER COLUMN celular SET NOT NULL');
            DB::statement('ALTER TABLE contactos_cliente ALTER COLUMN email SET NOT NULL');
        }

        if (Schema::hasColumn('contactos_cliente', 'dni')) {
            Schema::table('contactos_cliente', function (Blueprint $table) {
                $table->dropColumn('dni');
            });
        }

        if (Schema::hasColumn('clientes', 'direccion')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('direccion');
            });
        }
    }
};
