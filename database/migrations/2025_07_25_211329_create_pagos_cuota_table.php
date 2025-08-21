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
        Schema::create('pagos_cuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuota_id')->constrained('cuotas');
            $table->date('fecha_pago');
            $table->decimal('monto_pagado', 10, 2);
            $table->string('comprobante')->nullable(); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_cuota');
    }
};
