<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::apiResource('tipo-usuarios', App\Http\Controllers\TipoUsuarioController::class);
Route::apiResource('usuarios', App\Http\Controllers\UsuarioController::class);
Route::apiResource('productos', App\Http\Controllers\ProductoController::class);
Route::apiResource('producto-modulos', App\Http\Controllers\ProductoModuloController::class);
Route::apiResource('clientes', App\Http\Controllers\ClienteController::class);
Route::apiResource('contactos', App\Http\Controllers\ContactoController::class);
Route::apiResource('contratos', App\Http\Controllers\ContratoController::class);
Route::apiResource('cuotas', App\Http\Controllers\CuotaController::class);
Route::apiResource('avisos-saas', App\Http\Controllers\AvisoSaasController::class);
