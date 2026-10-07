<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function paymentNotifications()
    {
        return response()->json(['data' => [
            'notification_email' => Configuracion::where('clave', 'manual_payment_notification_email')->value('valor') ?: env('PAYMENT_REVIEW_EMAIL'),
        ]]);
    }

    public function updatePaymentNotifications(Request $request)
    {
        $validated = $request->validate(['notification_email' => ['nullable', 'email', 'max:255']]);
        Configuracion::updateOrCreate(
            ['clave' => 'manual_payment_notification_email'],
            ['valor' => $validated['notification_email'] ?? null]
        );
        return response()->json(['message' => 'Correo de notificaciones actualizado.', 'data' => $validated]);
    }
}
