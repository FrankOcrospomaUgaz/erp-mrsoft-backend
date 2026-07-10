<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturadores', function (Blueprint $table) {
            $table->id();
            $table->string('ruc')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion')->nullable();
            $table->string('usuario_sol')->nullable();
            $table->string('clave_sol')->nullable();
            $table->string('token')->nullable();
            $table->string('wsdl_factura')->nullable();
            $table->string('wsdl_boleta')->nullable();
            $table->string('wsdl_consulta')->nullable();
            $table->string('wsdl_bajas')->nullable();
            $table->enum('modo', ['simulacion', 'produccion'])->default('simulacion');
            $table->decimal('porcentaje_igv', 5, 2)->default(18);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturadores');
    }
};
