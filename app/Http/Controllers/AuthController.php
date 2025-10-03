<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Cuota;

class AuthController extends Controller
{

public function login(Request $request)
{
    $request->validate([
        'usuario'  => 'required|string',
        'password' => 'required|string',
    ]);

    $usuario = Usuario::where('usuario', $request->usuario)->first();

    if (!$usuario || !Hash::check($request->password, $usuario->password)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }

    // Autenticación correcta -> generar token
    $token = $usuario->createToken('api-token')->plainTextToken;

    $hoy = Carbon::today()->toDateString();

    // Buscar cuotas pendientes vencidas
    $cuotasPendientes = Cuota::with([
        'contrato.cliente',
    ])
    ->where('situacion', 'pendiente')
    ->where('fecha_vencimiento', '<', $hoy)
    ->get();

    // Actualizar su estado a vencido
    foreach ($cuotasPendientes as $cuota) {
        $cuota->situacion = 'vencido';
        $cuota->save();
    }

    // Armar detalle de las cuotas vencidas
    $detalle = $cuotasPendientes->map(function ($c) {
        $cliente = $c->contrato->cliente;
        return [
            'cuota_id'          => $c->id,
            'monto'             => $c->monto,
            'fecha_vencimiento' => $c->fecha_vencimiento->format('Y-m-d'),
            'contrato' => [
                'id'           => $c->contrato->id,
                'numero'       => $c->contrato->numero,
                'fecha_inicio' => $c->contrato->fecha_inicio?->format('Y-m-d'),
                'fecha_fin'    => $c->contrato->fecha_fin?->format('Y-m-d'),
            ],
            'cliente' => [
                'id'                    => $cliente->id,
                'razon_social'          => $cliente->razon_social,
                'dueno_nombre'          => $cliente->dueno_nombre,
                'dueno_celular'         => $cliente->dueno_celular,
                'dueno_email'           => $cliente->dueno_email,
                'representante_nombre'  => $cliente->representante_nombre,
                'representante_celular' => $cliente->representante_celular,
                'representante_email'   => $cliente->representante_email,
            ],
            'mensaje' => "⚠️ La cuota {$c->id} del contrato {$c->contrato->numero} está vencida. Contactar urgentemente al cliente {$cliente->razon_social} (Dueño: {$cliente->dueno_nombre}, Email: {$cliente->dueno_email}, Cel: {$cliente->dueno_celular}) para gestionar el pago.",
        ];
    });

    return response()->json([
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'usuario'      => $usuario,
        'cuotas_vencidas' => [
            'total'   => $detalle->count(),
            'detalle' => $detalle,
        ],
    ]);
}


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }


    public function authenticate(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            // Recupera el token enviado
            $token = $request->bearerToken();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'usuario' => $user,
            ]);
        } else {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    }
}
