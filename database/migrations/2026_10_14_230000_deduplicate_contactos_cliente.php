<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $contacts = DB::table('contactos_cliente')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $canonicalByIdentity = [];

        foreach ($contacts as $contact) {
            $dni = trim((string) ($contact->dni ?? ''));
            if ($dni === '') {
                continue;
            }

            // Normalize the DNI before grouping so spaces cannot create a second identity.
            if ($contact->dni !== $dni) {
                DB::table('contactos_cliente')->where('id', $contact->id)->update(['dni' => $dni]);
            }

            if (!isset($canonicalByIdentity[$dni])) {
                $canonicalByIdentity[$dni] = $contact;
                continue;
            }

            $canonical = $canonicalByIdentity[$dni];
            DB::table('contactos_cliente')->where('id', $canonical->id)->update([
                'nombre' => $canonical->nombre ?: $contact->nombre,
                'celular' => $canonical->celular ?: $contact->celular,
                'email' => $canonical->email ?: $contact->email,
                'es_dueno' => (bool) $canonical->es_dueno || (bool) $contact->es_dueno,
                'es_vendedor' => (bool) $canonical->es_vendedor || (bool) $contact->es_vendedor,
                'updated_at' => now(),
            ]);

            // Keep the canonical row and soft-delete the duplicate so old references remain recoverable.
            DB::table('contactos_cliente')->where('id', $contact->id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS contactos_cliente_dni_unique_active
                 ON contactos_cliente (dni)
                 WHERE deleted_at IS NULL AND dni IS NOT NULL AND btrim(dni) <> \'\''
            );
        } else {
            Schema::table('contactos_cliente', function (Blueprint $table) {
                $table->unique('dni', 'contactos_cliente_dni_unique_active');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS contactos_cliente_dni_unique_active');
        } else {
            Schema::table('contactos_cliente', function (Blueprint $table) {
                $table->dropUnique('contactos_cliente_dni_unique_active');
            });
        }
    }
};
