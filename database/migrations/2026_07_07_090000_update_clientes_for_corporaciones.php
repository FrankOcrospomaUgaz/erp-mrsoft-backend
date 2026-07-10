<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable()->after('razon_social');
            $table->boolean('dueno_es_representante')->default(false)->after('dueno_email');
            $table->boolean('dueno_es_responsable')->default(false)->after('dueno_es_representante');
            $table->string('responsable_nombre')->nullable()->after('representante_email');
            $table->string('responsable_celular')->nullable()->after('responsable_nombre');
            $table->string('responsable_email')->nullable()->after('responsable_celular');
        });

        Schema::table('sucursales_cliente', function (Blueprint $table) {
            $table->string('ruc')->nullable()->after('nombre');
            $table->string('razon_social')->nullable()->after('ruc');
            $table->string('nombre_comercial')->nullable()->after('razon_social');
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clientes ALTER COLUMN ruc DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN razon_social DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN dueno_celular DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN dueno_email DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN representante_nombre DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN representante_celular DROP NOT NULL');
            DB::statement('ALTER TABLE clientes ALTER COLUMN representante_email DROP NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE clientes MODIFY ruc VARCHAR(20) NULL');
            DB::statement('ALTER TABLE clientes MODIFY razon_social VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clientes MODIFY dueno_celular VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clientes MODIFY dueno_email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clientes MODIFY representante_nombre VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clientes MODIFY representante_celular VARCHAR(255) NULL');
            DB::statement('ALTER TABLE clientes MODIFY representante_email VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('sucursales_cliente', function (Blueprint $table) {
            $table->dropColumn(['ruc', 'razon_social', 'nombre_comercial']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_comercial',
                'dueno_es_representante',
                'dueno_es_responsable',
                'responsable_nombre',
                'responsable_celular',
                'responsable_email',
            ]);
        });
    }
};
