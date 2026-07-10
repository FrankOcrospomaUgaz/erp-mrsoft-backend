<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->nullOnDelete();
            $table->foreignId('facturador_id')->nullable()->constrained('facturadores')->nullOnDelete();
            $table->enum('tipo_documento', ['F', 'B'])->default('F');
            $table->string('serie', 8);
            $table->unsignedBigInteger('correlativo');
            $table->string('moneda', 3)->default('PEN');
            $table->enum('forma_pago', ['C', 'D'])->default('C');
            $table->date('fecha_emision');
            $table->time('hora_emision')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['E', 'R', 'M', 'T', 'I', 'U', 'V', 'X'])->default('E');
            $table->json('payload')->nullable();
            $table->json('sunat_request')->nullable();
            $table->json('sunat_response')->nullable();
            $table->string('solicitud_facturador_id')->nullable();
            $table->string('nombre_documento')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_text')->nullable();
            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tipo_documento', 'serie', 'correlativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
