<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_tipo_check');
            DB::statement('ALTER TABLE clientes ALTER COLUMN tipo TYPE VARCHAR(20) USING tipo::varchar');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_tipo_check CHECK (tipo IN ('corporacion', 'empresa', 'local', 'unico'))");
        }
    }
};
