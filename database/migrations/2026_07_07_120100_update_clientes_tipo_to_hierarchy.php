<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clientes ALTER COLUMN tipo TYPE VARCHAR(20) USING tipo::varchar');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE clientes MODIFY tipo VARCHAR(20) NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE clientes ALTER COLUMN tipo TYPE VARCHAR(20) USING tipo::varchar");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE clientes MODIFY tipo ENUM('corporacion','unico') NOT NULL");
        }
    }
};
