<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuditoriaDetalleRequest;
use App\Http\Requests\StoreAuditoriaRequest;
use App\Http\Requests\UpdateAuditoriaDetalleRequest;
use App\Http\Resources\AuditoriaDetalleResource;
use App\Http\Resources\AuditoriaResource;
use App\Models\Usuario;
use App\Services\AuditoriasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriasService $auditorias,
    ) {}

    private function actor(Request $request): Usuario
    {
        return $request->user();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $this->actor($request);

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return AuditoriaResource::collection($this->auditorias->listar($actor, $perPage));
    }

    public function store(StoreAuditoriaRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $auditoria = $this->auditorias->crear($actor, $request->validated());

        return response()->json([
            'message' => 'Auditoría creada correctamente.',
            'data' => new AuditoriaResource($auditoria->load('auditor')),
        ], 201);
    }

    public function show(Request $request, int $auditoria): JsonResponse
    {
        $actor = $this->actor($request);

        return response()->json([
            'data' => new AuditoriaResource($this->auditorias->obtener($actor, $auditoria)),
        ]);
    }

    public function registrarDetalle(StoreAuditoriaDetalleRequest $request, int $auditoria): JsonResponse
    {
        $actor = $this->actor($request);
        $detalle = $this->auditorias->registrarDetalle($actor, $auditoria, $request->validated());

        return response()->json([
            'message' => 'Bien registrado en la auditoría.',
            'data' => new AuditoriaDetalleResource($detalle),
        ], 201);
    }

    public function actualizarDetalle(UpdateAuditoriaDetalleRequest $request, int $auditoria, int $detalle): JsonResponse
    {
        $actor = $this->actor($request);
        $actualizado = $this->auditorias->actualizarDetalle($actor, $auditoria, $detalle, $request->validated());

        return response()->json([
            'message' => 'Detalle de auditoría actualizado.',
            'data' => new AuditoriaDetalleResource($actualizado),
        ]);
    }

    public function eliminarDetalle(Request $request, int $auditoria, int $detalle): JsonResponse
    {
        $actor = $this->actor($request);
        $this->auditorias->eliminarDetalle($actor, $auditoria, $detalle);

        return response()->json([
            'message' => 'Bien retirado de la auditoría.',
        ]);
    }
}
