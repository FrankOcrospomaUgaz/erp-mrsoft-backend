<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturadores', function (Blueprint $table) {
            if (!Schema::hasColumn('facturadores', 'empresa_id')) {
                $table->string('empresa_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturadores', function (Blueprint $table) {
            if (Schema::hasColumn('facturadores', 'empresa_id')) {
                $table->dropColumn('empresa_id');
            }
        });
    }
};
