<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBienRequest;
use App\Http\Requests\UpdateBienRequest;
use App\Http\Resources\BienResource;
use App\Http\Resources\BienResumenResource;
use App\Models\Usuario;
use App\Services\BienesService;
use App\Services\FotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BienController extends Controller
{
    public function __construct(
        private readonly BienesService $bienes,
        private readonly FotoService $fotos,
    ) {}

    /**
     * El actor llega autenticado vía Sanctum (middleware auth:sanctum).
     */
    private function actor(Request $request): Usuario
    {
        return $request->user();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $this->actor($request);

        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $datos = $request->validate([
            'vista' => ['nullable', 'in:'.implode(',', BienesService::VISTAS)],
            'cve_aula' => ['nullable', 'integer', 'exists:aula,cve_aula'],
            'cve_edificio' => ['nullable', 'integer', 'exists:edificio,cve_edificio'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($datos['per_page'] ?? 20);

        return BienResumenResource::collection(
            $this->bienes->listar($actor, $perPage, $datos),
        );
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

    public function restaurar(Request $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);
        $bien = $this->bienes->restaurar($actor, $bien);

        return response()->json([
            'message' => 'Bien restaurado desde la papelera.',
            'data' => new BienResource($bien),
        ]);
    }

    public function purgar(Request $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);
        $this->bienes->purgar($actor, $bien);

        return response()->json([
            'message' => 'Bien eliminado definitivamente.',
        ]);
    }

    /**
     * Resolución de un código escaneado (QR, código de barras o
     * identificador) al detalle completo del bien. Es la función que
     * consume el escáner de la PWA al leer un código.
     */
    public function porCodigo(Request $request, string $codigo): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $bien = $this->bienes->obtenerPorCodigo($actor, $codigo);

        return response()->json([
            'data' => new BienResource($bien),
            'qr_url' => $this->bienes->urlQr($bien),
        ]);
    }

    /**
     * Sube (o reemplaza) la foto del bien. La imagen se guarda en el
     * disco configurado (Google Drive en producción) y se actualizan
     * foto_drive_id / foto_url.
     */
    public function subirFoto(Request $request, int $bien): JsonResponse
    {
        $actor = $this->actor($request);

        $datos = $request->validate([
            'foto' => ['required', 'image', 'mimes:'.implode(',', FotoService::EXTENSIONES), 'max:'.FotoService::MAX_KILOBYTES],
        ]);

        $bien = $this->bienes->guardarFoto($actor, $bien, $datos['foto']);

        return response()->json([
            'message' => 'Foto del bien guardada correctamente.',
            'data' => new BienResource($bien->fresh()),
        ]);
    }

    /**
     * Endpoint proxy de la foto: streamea el archivo del disco de
     * almacenamiento respetando la autorización por rol/propiedad.
     */
    public function foto(Request $request, int $bien): StreamedResponse
    {
        $actor = $this->actor($request);

        $bien = $this->bienes->obtener($actor, $bien);

        return $this->fotos->respuesta($bien);
    }
}
