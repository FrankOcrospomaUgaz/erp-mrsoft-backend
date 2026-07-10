<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobante_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('modulo_id')->nullable()->constrained('modulos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('tipo_igv', 4)->default('10');
            $table->string('unidad', 8)->default('NIU');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_detalles');
    }
};
