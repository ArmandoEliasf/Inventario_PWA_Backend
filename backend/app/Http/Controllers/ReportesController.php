<?php

namespace App\Http\Controllers;

use App\Services\BienesService;
use App\Services\ReportesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    public function __construct(
        private readonly ReportesService $reportes,
    ) {}

    /**
     * GET /api/reportes/resguardo-individual/{persona}?cve_aula=X
     */
    public function resguardoIndividual(Request $request, int $persona): JsonResponse
    {
        $datos = $request->validate([
            'cve_aula' => ['nullable', 'integer', 'exists:aula,cve_aula'],
        ]);

        return response()->json([
            'data' => $this->reportes->resguardoIndividual($request->user(), $persona, $datos['cve_aula'] ?? null),
        ]);
    }

    /**
     * GET /api/reportes/encabezado-reporte/{persona}
     */
    public function encabezadoReporte(Request $request, int $persona): JsonResponse
    {
        return response()->json([
            'data' => $this->reportes->encabezadoReporte($request->user(), $persona),
        ]);
    }

    /**
     * GET /api/reportes/encabezado-direccion/{persona}
     */
    public function encabezadoDireccion(Request $request, int $persona): JsonResponse
    {
        return response()->json([
            'data' => $this->reportes->encabezadoDireccion($request->user(), $persona),
        ]);
    }

    /**
     * GET /api/reportes/movimiento-interno/{prestamo}
     */
    public function movimientoInterno(Request $request, int $prestamo): JsonResponse
    {
        return response()->json([
            'data' => $this->reportes->movimientoInterno($request->user(), $prestamo),
        ]);
    }

    /**
     * GET /api/reportes/bajas?cve_adscripcion=X
     */
    public function bienesBaja(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->bienesBaja($request->user(), $datos['cve_adscripcion'] ?? null),
        ]);
    }

    /**
     * GET /api/reportes/mantenimiento?estado_fisico=X&cve_adscripcion=Y
     */
    public function bienesMantenimiento(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado_fisico' => ['nullable', 'string', 'in:'.implode(',', BienesService::ESTADOS_FISICOS)],
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->bienesMantenimiento(
                $request->user(),
                $datos['estado_fisico'] ?? 'deteriorado',
                $datos['cve_adscripcion'] ?? null,
            ),
        ]);
    }

    /**
     * GET /api/reportes/trazabilidad/{bien}
     */
    public function trazabilidad(Request $request, int $bien): JsonResponse
    {
        return response()->json([
            'data' => $this->reportes->trazabilidad($request->user(), $bien),
        ]);
    }

    /**
     * GET /api/reportes/valor-por-aula?cve_adscripcion=X
     */
    public function valorPorAula(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->valorPorAula($request->user(), $datos['cve_adscripcion'] ?? null),
        ]);
    }

    /**
     * GET /api/reportes/prestamos-no-devueltos?cve_adscripcion=X
     */
    public function prestamosNoDevueltos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->prestamosNoDevueltos($request->user(), $datos['cve_adscripcion'] ?? null),
        ]);
    }

    /**
     * GET /api/reportes/conteo-estado-fisico?cve_adscripcion=X
     */
    public function conteoEstadoFisico(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->conteoEstadoFisico($request->user(), $datos['cve_adscripcion'] ?? null),
        ]);
    }

    /**
     * GET /api/reportes/conteo-estado-prestamo?cve_adscripcion=X
     */
    public function conteoEstadoPrestamo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cve_adscripcion' => ['nullable', 'integer', 'exists:adscripcion,cve_adscripcion'],
        ]);

        return response()->json([
            'data' => $this->reportes->conteoEstadoPrestamo($request->user(), $datos['cve_adscripcion'] ?? null),
        ]);
    }
}
