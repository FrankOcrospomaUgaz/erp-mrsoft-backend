<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanio')->nullable();
            $table->timestamps();
            $table->index(['contrato_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_documentos');
    }
};
