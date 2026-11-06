<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saas_branch_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('provider', 40)->default('hotelhub');
            $table->string('external_branch_id', 100);
            $table->string('branch_name');
            $table->boolean('active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_branch_id']);
            $table->index(['cliente_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_branch_integrations');
    }
};
