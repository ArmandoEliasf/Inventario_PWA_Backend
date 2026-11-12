<?php

namespace App\Http\Controllers;

use App\Services\BienesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AulaController extends Controller
{
    public function __construct(
        private readonly BienesService $bienes,
    ) {}

    /**
     * Aulas para las pestañas "bienes por aula" con conteo de bienes.
     * - Profesor: solo las aulas donde tiene bienes propios.
     * - Admin: todas las aulas; opcionalmente de un edificio (?cve_edificio=X).
     */
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $cveEdificio = $request->query('cve_edificio');
        $aulas = $this->bienes->aulasDe($actor, $cveEdificio !== null ? (int) $cveEdificio : null);

        return response()->json(['data' => $aulas]);
    }
}
