<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ReportesController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('aulas', [AulaController::class, 'index']);

    Route::apiResource('bienes', BienController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->parameters(['bienes' => 'bien']);

    Route::get('bienes/por-codigo/{codigo}', [BienController::class, 'porCodigo'])
        ->where('codigo', '.+');
    Route::put('bienes/{bien}/restaurar', [BienController::class, 'restaurar']);
    Route::delete('bienes/{bien}/purgar', [BienController::class, 'purgar']);

    Route::get('auditorias', [AuditoriaController::class, 'index']);
    Route::post('auditorias', [AuditoriaController::class, 'store']);
    Route::get('auditorias/{auditoria}', [AuditoriaController::class, 'show'])
        ->whereNumber('auditoria');
    Route::post('auditorias/{auditoria}/detalles', [AuditoriaController::class, 'registrarDetalle'])
        ->whereNumber('auditoria');
    Route::put('auditorias/{auditoria}/detalles/{detalle}', [AuditoriaController::class, 'actualizarDetalle'])
        ->whereNumber(['auditoria', 'detalle']);
    Route::delete('auditorias/{auditoria}/detalles/{detalle}', [AuditoriaController::class, 'eliminarDetalle'])
        ->whereNumber(['auditoria', 'detalle']);

    Route::get('prestamos', [PrestamoController::class, 'index']);
    Route::get('prestamos/{prestamo}', [PrestamoController::class, 'show'])
        ->whereNumber('prestamo');
    Route::post('prestamos', [PrestamoController::class, 'store']);
    Route::post('prestamos/{prestamo}/aceptar', [PrestamoController::class, 'aceptar'])
        ->whereNumber('prestamo');
    Route::post('prestamos/{prestamo}/devolver', [PrestamoController::class, 'devolver'])
        ->whereNumber('prestamo');
    Route::post('prestamos/{prestamo}/confirmar-devolucion', [PrestamoController::class, 'confirmarDevolucion'])
        ->whereNumber('prestamo');

    // ----- Reportes (datos crudos para los formatos que arma el front) -----
    Route::get('reportes/resguardo-individual/{persona}', [ReportesController::class, 'resguardoIndividual'])
        ->whereNumber('persona');
    Route::get('reportes/encabezado-reporte/{persona}', [ReportesController::class, 'encabezadoReporte'])
        ->whereNumber('persona');
    Route::get('reportes/encabezado-direccion/{persona}', [ReportesController::class, 'encabezadoDireccion'])
        ->whereNumber('persona');
    Route::get('reportes/movimiento-interno/{prestamo}', [ReportesController::class, 'movimientoInterno'])
        ->whereNumber('prestamo');
    Route::get('reportes/bajas', [ReportesController::class, 'bienesBaja']);
    Route::get('reportes/mantenimiento', [ReportesController::class, 'bienesMantenimiento']);
    Route::get('reportes/trazabilidad/{bien}', [ReportesController::class, 'trazabilidad'])
        ->whereNumber('bien');
    Route::get('reportes/valor-por-aula', [ReportesController::class, 'valorPorAula']);
    Route::get('reportes/prestamos-no-devueltos', [ReportesController::class, 'prestamosNoDevueltos']);
    Route::get('reportes/conteo-estado-fisico', [ReportesController::class, 'conteoEstadoFisico']);
    Route::get('reportes/conteo-estado-prestamo', [ReportesController::class, 'conteoEstadoPrestamo']);
});
