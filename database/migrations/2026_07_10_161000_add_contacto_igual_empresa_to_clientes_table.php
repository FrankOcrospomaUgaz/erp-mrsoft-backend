<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'contacto_igual_empresa')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->boolean('contacto_igual_empresa')->default(false)->after('direccion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'contacto_igual_empresa')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('contacto_igual_empresa');
            });
        }
    }
};
