<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->unsignedBigInteger('cliente_id')->nullable()->change();
            $table->string('receptor_documento', 20)->nullable()->after('cliente_id');
            $table->string('receptor_nombre')->nullable()->after('receptor_documento');
            $table->string('receptor_direccion')->nullable()->after('receptor_nombre');
            $table->index('receptor_documento');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex(['receptor_documento']);
            $table->dropColumn(['receptor_documento', 'receptor_nombre', 'receptor_direccion']);
            $table->foreignId('cliente_id')->nullable(false)->change();
            $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnUpdate()->restrictOnDelete();
        });
    }
};
