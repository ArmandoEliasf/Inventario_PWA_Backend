<?php

namespace App\Http\Controllers;

use App\Services\InformacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformacionController extends Controller
{
    public function __construct(
        private readonly InformacionService $informacion,
    ) {}

    /**
     * GET /api/aulas/edificio/{edificio}
     * Aulas (id y nombre) de un edificio para el select en cascada.
     */
    public function aulasPorEdificio(int $edificio): JsonResponse
    {
        return response()->json(['data' => $this->informacion->aulasPorEdificio($edificio)]);
    }

    /**
     * GET /api/informacion/aulas
     * Aulas con edificio, tipo, capacidad y adscripción.
     */
    public function aulas(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->informacion->aulas($request->user())]);
    }

    /**
     * GET /api/informacion/personal/carrera/{carrera}
     * Personal (id y nombre completo) de una carrera.
     */
    public function personalPorCarrera(int $carrera): JsonResponse
    {
        return response()->json(['data' => $this->informacion->personalPorCarrera($carrera)]);
    }

    /**
     * GET /api/informacion/profesores
     * Profesores activos (id y nombre completo).
     */
    public function profesores(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->profesores()]);
    }

    /**
     * GET /api/informacion/bienes?cve_aula=X
     * Catálogo de bienes (id y nombre) para dropdowns.
     */
    public function bienesCatalogo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_aula' => ['nullable', 'integer', 'exists:aula,cve_aula'],
        ]);

        return response()->json([
            'data' => $this->informacion->bienesCatalogo($request->user(), $datos['cve_aula'] ?? null),
        ]);
    }

    /**
     * GET /api/informacion/bienes/disponibles?cve_aula=X
     * Bienes sin préstamo activo, para solicitar préstamos.
     */
    public function bienesDisponibles(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_aula' => ['nullable', 'integer', 'exists:aula,cve_aula'],
        ]);

        return response()->json([
            'data' => $this->informacion->bienesDisponibles($datos['cve_aula'] ?? null),
        ]);
    }

    /** GET /api/informacion/tipos-bien */
    public function tiposBien(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->tiposBien()]);
    }

    /** GET /api/informacion/familias/tipo/{tipo} */
    public function familiasPorTipo(int $tipo): JsonResponse
    {
        return response()->json(['data' => $this->informacion->familiasPorTipo($tipo)]);
    }

    /** GET /api/informacion/articulos/familia/{familia} */
    public function articulosPorFamilia(int $familia): JsonResponse
    {
        return response()->json(['data' => $this->informacion->articulosPorFamilia($familia)]);
    }

    /** GET /api/informacion/edificios */
    public function edificios(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->edificios()]);
    }

    /** GET /api/informacion/tipos-aula */
    public function tiposAula(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->tiposAula()]);
    }

    /** GET /api/informacion/marcas */
    public function marcas(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->marcas()]);
    }

    /** GET /api/informacion/modelos */
    public function modelos(): JsonResponse
    {
        return response()->json(['data' => $this->informacion->modelos()]);
    }

    /** GET /api/informacion/bien/qr/{bien} */
    public function qrBien(int $bien): JsonResponse
    {
        return response()->json(['data' => $this->informacion->qrDeBien($bien)]);
    }

    /**
     * GET /api/informacion/valor-bienes-cargo/{aula}
     * Bienes de un aula agrupados + monto total.
     */
    public function valorBienesCargo(Request $request, int $aula): JsonResponse
    {
        return response()->json(['data' => $this->informacion->valorBienesCargo($request->user(), $aula)]);
    }

    /**
     * GET /api/informacion/valor-bienes-cargo-detalle/{aula}
     * Bienes individuales de un aula con costo y foto.
     */
    public function valorBienesCargoDetalle(Request $request, int $aula): JsonResponse
    {
        return response()->json(['data' => $this->informacion->valorBienesCargoDetalle($request->user(), $aula)]);
    }

    /**
     * GET /api/informacion/bienes-por-aula/{aula}
     * Bienes del aula con marca, modelo, encargado y estados.
     */
    public function bienesPorAula(Request $request, int $aula): JsonResponse
    {
        return response()->json(['data' => $this->informacion->bienesPorAula($request->user(), $aula)]);
    }
}
