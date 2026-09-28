<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('no_facturado')->default(false)->after('contacto_igual_empresa');
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('no_facturado')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', fn (Blueprint $table) => $table->dropColumn('no_facturado'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('no_facturado'));
    }
};
