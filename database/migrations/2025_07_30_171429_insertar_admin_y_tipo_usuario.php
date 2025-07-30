<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insertar tipo de usuario "Administrador"
        $tipoUsuarioId = DB::table('tipos_usuario')->insertGetId([
            'nombre' => 'Administrador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar usuario admin con ese tipo de usuario
        DB::table('usuarios')->insert([
            'nombres' => 'Frank',
            'apellidos' => 'Ocrospoma Ugaz',
            'usuario' => 'admin',
            'password' => Hash::make('admin123'), // puedes cambiar la contraseña si lo deseas
            'tipo_usuario_id' => $tipoUsuarioId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el usuario admin si existe
        DB::table('usuarios')->where('usuario', 'admin')->delete();

        // Eliminar el tipo de usuario "Administrador"
        DB::table('tipos_usuario')->where('nombre', 'Administrador')->delete();
    }
};
