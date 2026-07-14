<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_ruc_unique');
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS clientes_ruc_unique_active ON clientes (ruc) WHERE deleted_at IS NULL AND ruc IS NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS clientes_ruc_unique_active');
            DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_ruc_unique UNIQUE (ruc)');
        }
    }
};
