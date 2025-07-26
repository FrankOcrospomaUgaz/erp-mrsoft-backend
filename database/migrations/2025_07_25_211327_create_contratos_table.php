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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('numero')->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->enum('tipo_contrato', ['desarrollo', 'saas', 'soporte']);
            $table->decimal('total', 10, 2);
            $table->enum('forma_pago', ['unico', 'parcial']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
