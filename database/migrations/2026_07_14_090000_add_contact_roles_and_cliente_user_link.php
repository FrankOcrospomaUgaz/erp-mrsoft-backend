<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos_cliente', function (Blueprint $table) {
            if (!Schema::hasColumn('contactos_cliente', 'es_dueno')) {
                $table->boolean('es_dueno')->default(false)->after('email');
            }

            if (!Schema::hasColumn('contactos_cliente', 'es_vendedor')) {
                $table->boolean('es_vendedor')->default(false)->after('es_dueno');
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'cliente_id')) {
                $table->foreignId('cliente_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clientes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'cliente_id')) {
                $table->dropConstrainedForeignId('cliente_id');
            }
        });

        Schema::table('contactos_cliente', function (Blueprint $table) {
            if (Schema::hasColumn('contactos_cliente', 'es_vendedor')) {
                $table->dropColumn('es_vendedor');
            }

            if (Schema::hasColumn('contactos_cliente', 'es_dueno')) {
                $table->dropColumn('es_dueno');
            }
        });
    }
};
