<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['corporacion', 'unico']);
            $table->string('ruc')->unique();
            $table->string('razon_social');
            $table->string('dueno_nombre');
            $table->string('dueno_celular');
            $table->string('dueno_email');
            $table->string('representante_nombre');
            $table->string('representante_celular');
            $table->string('representante_email');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
