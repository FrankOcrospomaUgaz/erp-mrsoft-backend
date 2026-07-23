<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->string('estado_envio_cliente', 30)->default('pendiente')->after('estado');
            $table->timestamp('fecha_envio_cliente')->nullable()->after('estado_envio_cliente');
            $table->string('celular_envio_cliente', 30)->nullable()->after('fecha_envio_cliente');
            $table->text('error_envio_cliente')->nullable()->after('celular_envio_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropColumn([
                'estado_envio_cliente',
                'fecha_envio_cliente',
                'celular_envio_cliente',
                'error_envio_cliente',
            ]);
        });
    }
};
