<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const RECEIPT_PATH = 'comprobantes/pago-por-carga-de-datos.png';

    public function up(): void
    {
        $receipt = file_get_contents(__DIR__ . '/assets/pago-por-carga-de-datos.png');
        if ($receipt === false) {
            throw new RuntimeException('No se encontró la imagen de pago por carga de datos.');
        }

        Storage::disk('public')->put(self::RECEIPT_PATH, $receipt);
        $today = now()->toDateString();

        DB::transaction(function () use ($today) {
            $installments = DB::table('cuotas as q')
                ->join('comprobantes as c', 'c.cuota_id', '=', 'q.id')
                ->whereNull('q.deleted_at')
                ->whereNull('c.deleted_at')
                ->where('q.situacion', '!=', 'pagado')
                ->select('q.id', 'q.monto', 'q.fecha_pago')
                ->distinct()
                ->orderBy('q.id')
                ->get();

            foreach ($installments as $installment) {
                $approvedPayments = DB::table('pagos_cuota')
                    ->where('cuota_id', $installment->id)
                    ->whereNull('deleted_at')
                    ->where('estado_revision', 'aprobado');
                $approvedAmount = (float) (clone $approvedPayments)->sum('monto_pagado');
                $remaining = round(max(0, (float) $installment->monto - $approvedAmount), 2);

                if ($remaining > 0) {
                    DB::table('pagos_cuota')->insert([
                        'cuota_id' => $installment->id,
                        'fecha_pago' => $today,
                        'monto_pagado' => $remaining,
                        'comprobante' => self::RECEIPT_PATH,
                        'metodo_pago' => 'manual',
                        'estado_revision' => 'aprobado',
                        'observacion_revision' => 'Pago regularizado por carga de datos: la cuota ya tenía una factura asignada.',
                        'revisado_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $paymentWithoutReceipt = (clone $approvedPayments)
                        ->where(function ($query) {
                            $query->whereNull('comprobante')->orWhere('comprobante', '');
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($paymentWithoutReceipt) {
                        DB::table('pagos_cuota')->where('id', $paymentWithoutReceipt->id)->update([
                            'comprobante' => self::RECEIPT_PATH,
                            'observacion_revision' => 'Constancia genérica añadida por carga de datos: la cuota ya tenía una factura asignada.',
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('cuotas')->where('id', $installment->id)->update([
                    'situacion' => 'pagado',
                    'fecha_pago' => $installment->fecha_pago ?: $today,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Es una regularización histórica; no se revierten pagos ni estados de cuotas.
    }
};
