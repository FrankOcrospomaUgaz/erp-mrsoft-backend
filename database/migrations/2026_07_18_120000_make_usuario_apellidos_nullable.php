<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'apellidos')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE usuarios ALTER COLUMN apellidos DROP NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE usuarios MODIFY apellidos VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('usuarios', 'apellidos')) {
            return;
        }

        DB::table('usuarios')
            ->whereNull('apellidos')
            ->update(['apellidos' => '']);

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE usuarios ALTER COLUMN apellidos SET NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE usuarios MODIFY apellidos VARCHAR(255) NOT NULL');
        }
    }
};
