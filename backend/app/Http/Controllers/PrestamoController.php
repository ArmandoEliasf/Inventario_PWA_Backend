<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrestamoRequest;
use App\Http\Resources\PrestamoResource;
use App\Models\Usuario;
use App\Services\PrestamosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrestamoController extends Controller
{
    public function __construct(
        private readonly PrestamosService $prestamos,
    ) {}

    private function actor(Request $request): Usuario
    {
        return $request->user();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $this->actor($request);

        $vista = $request->query('vista', PrestamosService::VISTA_SOLICITADOS);

        if (! in_array($vista, PrestamosService::VISTAS, true)) {
            abort(422, "La vista '{$vista}' no es válida.");
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return PrestamoResource::collection($this->prestamos->listar($actor, $vista, $perPage));
    }

    public function store(StorePrestamoRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $prestamo = $this->prestamos->solicitar($actor, $request->validated());

        $estado = $prestamo->estado_prestamo;

        return response()->json([
            'message' => $estado === 'aprobado'
                ? 'Préstamo aprobado automáticamente (solicitante administrador).'
                : 'Solicitud de préstamo registrada, pendiente de aceptación.',
            'data' => new PrestamoResource($prestamo),
        ], 201);
    }

    public function aceptar(Request $request, int $prestamo): JsonResponse
    {
        $actor = $this->actor($request);
        $actualizado = $this->prestamos->aceptar($actor, $prestamo);

        return response()->json([
            'message' => 'Préstamo aprobado.',
            'data' => new PrestamoResource($actualizado),
        ]);
    }

    public function devolver(Request $request, int $prestamo): JsonResponse
    {
        $actor = $this->actor($request);
        $actualizado = $this->prestamos->devolver($actor, $prestamo);

        $cerrado = $actualizado->estado_prestamo === 'devuelto';

        return response()->json([
            'message' => $cerrado
                ? 'Devolución registrada y confirmada (administrador).'
                : 'Devolución registrada, pendiente de confirmación del encargado.',
            'data' => new PrestamoResource($actualizado),
        ]);
    }

    public function confirmarDevolucion(Request $request, int $prestamo): JsonResponse
    {
        $actor = $this->actor($request);
        $actualizado = $this->prestamos->confirmarDevolucion($actor, $prestamo);

        return response()->json([
            'message' => 'Devolución confirmada, préstamo finalizado.',
            'data' => new PrestamoResource($actualizado),
        ]);
    }
}
