<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_locales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('codigo')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('tipos_locales')->insert([
            [
                'nombre' => 'Restaurante',
                'codigo' => 'restaurante',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Hotel',
                'codigo' => 'hotel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'Punto de venta',
                'codigo' => 'punto_venta',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_locales');
    }
};
