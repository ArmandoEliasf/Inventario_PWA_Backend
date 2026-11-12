<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Http\Resources\BienResource;
use App\Models\Usuario;
use App\Services\BienesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BienController extends Controller
{
    public function __construct(
        private readonly BienesService $bienes,
    ) {}

    /**
     * Resuelve el actor. TEMPORAL: mientras no exista autenticación,
     * el usuario llega por el header X-Usuario (cve_usuario).
     * TODO: reemplazar por $request->user() cuando se integre auth.
     */
    private function actor(Request $request): Usuario
    {
        $cveUsuario = $request->header('X-Usuario');

        if (! $cveUsuario || ! is_numeric($cveUsuario)) {
            abort(401, 'Falta el header X-Usuario (identificador del usuario).');
        }

        $usuario = Usuario::query()->with('role')->find((int) $cveUsuario);

        if (! $usuario || ! $usuario->activo) {
            abort(401, 'Usuario inválido o inactivo.');
        }

        return $usuario;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $this->actor($request);

        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        return BienResource::collection($this->bienes->listar($actor, $perPage));
    }

    public function show(Request $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $bien = $this->bienes->obtener($actor, $bien);

        return response()->json([
            'data' => new BienResource($bien),
            'qr_url' => $this->bienes->urlQr($bien),
        ]);
    }

    public function store(StoreBienRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $bien = $this->bienes->crear($actor, $request->validated());

        return response()->json([
            'message' => 'Bien creado correctamente.',
            'data' => new BienResource($bien),
            'qr_url' => $this->bienes->urlQr($bien),
        ], 201);
    }

    public function update(UpdateBienRequest $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);
        $bien = $this->bienes->editar($actor, $bien, $request->validated());

        return response()->json([
            'message' => 'Bien actualizado correctamente.',
            'data' => new BienResource($bien),
        ]);
    }

    public function destroy(Request $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);
        $bien = $this->bienes->enviarPapelera($actor, $bien);

        return response()->json([
            'message' => 'Bien enviado a la papelera.',
            'data' => new BienResource($bien),
        ]);
    }
}
