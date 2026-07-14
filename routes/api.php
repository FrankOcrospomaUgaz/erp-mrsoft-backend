<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/authenticate', [AuthController::class, 'authenticate']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/resumen', [App\Http\Controllers\DashboardController::class, 'resumen']);
     Route::apiResource('tipo-usuarios', App\Http\Controllers\TipoUsuarioController::class);
    Route::apiResource('tipos-local', App\Http\Controllers\TipoLocalController::class);
    Route::apiResource('usuarios', App\Http\Controllers\UsuarioController::class);
    Route::apiResource('notificaciones', App\Http\Controllers\NotificacionesController::class);
    Route::apiResource('productos', App\Http\Controllers\ProductoController::class);
    Route::apiResource('producto-modulos', App\Http\Controllers\ProductoModuloController::class);
    Route::post('comprobantes/emision-masiva', [App\Http\Controllers\ComprobanteController::class, 'emisionMasiva']);
    Route::post('comprobantes/reenviar-pendientes', [App\Http\Controllers\ComprobanteController::class, 'reenviarPendientes']);
    Route::post('comprobantes/{id}/emitir', [App\Http\Controllers\ComprobanteController::class, 'emitir']);
    Route::get('comprobantes/{id}/download-xml', [App\Http\Controllers\ComprobanteController::class, 'downloadXml']);
    Route::get('comprobantes/{id}/download-cdr', [App\Http\Controllers\ComprobanteController::class, 'downloadCdr']);
    Route::apiResource('comprobantes', App\Http\Controllers\ComprobanteController::class)->only(['index', 'store', 'show']);
    Route::get('facturadores/activo', [App\Http\Controllers\FacturadorController::class, 'activo']);
    Route::match(['post', 'put'], 'facturadores/activo', [App\Http\Controllers\FacturadorController::class, 'guardarActivo']);
    Route::apiResource('facturadores', App\Http\Controllers\FacturadorController::class);
    Route::get('clientes/consulta-ruc/{ruc}', [App\Http\Controllers\ClienteController::class, 'consultarRuc']);
    Route::get('clientes/consulta-dni/{dni}', [App\Http\Controllers\ClienteController::class, 'consultarDni']);
    Route::apiResource('clientes', App\Http\Controllers\ClienteController::class);
    Route::get('clientes/{id}/sucursales', [App\Http\Controllers\ClienteController::class, 'sucursalesPorCliente']);
    Route::apiResource('pagos', App\Http\Controllers\PagoCuotumController::class);
    Route::get('contratos/siguiente-numero', [App\Http\Controllers\ContratoController::class, 'siguienteNumero']);
    Route::get('contratos/{id}/pdf', [App\Http\Controllers\ContratoController::class, 'pdf']);
    Route::apiResource('contratos', App\Http\Controllers\ContratoController::class);
    Route::apiResource('cuotas', App\Http\Controllers\CuotaController::class);

    Route::apiResource('avisos-saas', App\Http\Controllers\AvisoSaasController::class);

});
