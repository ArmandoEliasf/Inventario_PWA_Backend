<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\InformacionController;
use App\Http\Controllers\PrestamoController;
use App\Http\Controllers\ReportesController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::get('aulas', [AulaController::class, 'index']);
    Route::get('aulas/edificio/{edificio}', [InformacionController::class, 'aulasPorEdificio'])
        ->whereNumber('edificio');

    Route::apiResource('bienes', BienController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->parameters(['bienes' => 'bien']);

    Route::get('bienes/por-codigo/{codigo}', [BienController::class, 'porCodigo'])
        ->where('codigo', '.+');
    Route::put('bienes/{bien}/restaurar', [BienController::class, 'restaurar']);
    Route::delete('bienes/{bien}/purgar', [BienController::class, 'purgar']);

    // Foto del bien: subir/reemplazar y proxy de lectura (la imagen
    // vive en Google Drive y se sirve con la autorización del actor).
    Route::post('bienes/{bien}/foto', [BienController::class, 'subirFoto'])
        ->whereNumber('bien');
    Route::get('bienes/{bien}/foto', [BienController::class, 'foto'])
        ->whereNumber('bien');

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

    // ----- Información (catálogos y datos para rellenar formularios) -----
    Route::prefix('informacion')->group(function () {
        Route::get('aulas', [InformacionController::class, 'aulas']);
        Route::get('personal/carrera/{carrera}', [InformacionController::class, 'personalPorCarrera'])
            ->whereNumber('carrera');
        Route::get('profesores', [InformacionController::class, 'profesores']);
        Route::get('bienes', [InformacionController::class, 'bienesCatalogo']);
        Route::get('bienes/disponibles', [InformacionController::class, 'bienesDisponibles']);
        Route::get('tipos-bien', [InformacionController::class, 'tiposBien']);
        Route::get('familias/tipo/{tipo}', [InformacionController::class, 'familiasPorTipo'])
            ->whereNumber('tipo');
        Route::get('articulos/familia/{familia}', [InformacionController::class, 'articulosPorFamilia'])
            ->whereNumber('familia');
        Route::get('edificios', [InformacionController::class, 'edificios']);
        Route::get('tipos-aula', [InformacionController::class, 'tiposAula']);
        Route::get('marcas', [InformacionController::class, 'marcas']);
        Route::get('modelos', [InformacionController::class, 'modelos']);
        Route::get('bien/qr/{bien}', [InformacionController::class, 'qrBien'])
            ->whereNumber('bien');
        Route::get('valor-bienes-cargo/{aula}', [InformacionController::class, 'valorBienesCargo'])
            ->whereNumber('aula');
        Route::get('valor-bienes-cargo-detalle/{aula}', [InformacionController::class, 'valorBienesCargoDetalle'])
            ->whereNumber('aula');
        Route::get('bienes-por-aula/{aula}', [InformacionController::class, 'bienesPorAula'])
            ->whereNumber('aula');
    });
});
