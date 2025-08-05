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
     Route::apiResource('tipo-usuarios', App\Http\Controllers\TipoUsuarioController::class);
    Route::apiResource('usuarios', App\Http\Controllers\UsuarioController::class);
    Route::apiResource('notificaciones', App\Http\Controllers\NotificacionesController::class);
    Route::apiResource('productos', App\Http\Controllers\ProductoController::class);
    Route::apiResource('producto-modulos', App\Http\Controllers\ProductoModuloController::class);
    Route::apiResource('clientes', App\Http\Controllers\ClienteController::class);
    Route::get('clientes/{id}/sucursales', [App\Http\Controllers\ClienteController::class, 'sucursalesPorCliente']);
    Route::apiResource('pagos', App\Http\Controllers\PagoCuotumController::class);
    Route::apiResource('contratos', App\Http\Controllers\ContratoController::class);
    Route::apiResource('cuotas', App\Http\Controllers\CuotaController::class);

    Route::apiResource('avisos-saas', App\Http\Controllers\AvisoSaasController::class);

});
