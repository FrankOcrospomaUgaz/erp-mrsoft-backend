<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_ruc_unique');
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique');
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique_active');
            DB::statement(
                "CREATE UNIQUE INDEX clientes_ruc_unique_non_local_active
                 ON clientes (ruc)
                 WHERE deleted_at IS NULL
                   AND ruc IS NOT NULL
                   AND tipo NOT IN ('local', 'unico')"
            );
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique('clientes_ruc_unique');
            });
            DB::statement(
                "CREATE UNIQUE INDEX clientes_ruc_unique_non_local_active
                 ON clientes (ruc)
                 WHERE deleted_at IS NULL
                   AND ruc IS NOT NULL
                   AND tipo NOT IN ('local', 'unico')"
            );
            return;
        }

        if ($driver === 'mysql') {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique('clientes_ruc_unique');
            });
        }
    }

    public function down(): void
    {
        $duplicates = DB::table('clientes')
            ->select('ruc')
            ->whereNotNull('ruc')
            ->whereNull('deleted_at')
            ->groupBy('ruc')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Cannot restore global RUC uniqueness while duplicate client RUCs exist.');
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique_non_local_active');
            DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_ruc_unique UNIQUE (ruc)');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique_non_local_active');
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('ruc', 'clientes_ruc_unique');
            });
            return;
        }

        if ($driver === 'mysql') {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('ruc', 'clientes_ruc_unique');
            });
        }
    }
};
