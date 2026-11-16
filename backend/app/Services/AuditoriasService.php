<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\AuditoriaDetalle;
use App\Models\Bien;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Módulo de auditorías físicas del inventario.
 *
 * Flujo de la PWA:
 * 1. Se crea una auditoría (POST /api/auditorias) y el actor queda como auditor.
 * 2. Se escanean QR / códigos de barras de los bienes (GET /api/bienes/por-codigo/{codigo}),
 *    que devuelve el detalle completo del bien.
 * 3. Cada bien escaneado se registra en la auditoría
 *    (POST /api/auditorias/{auditoria}/detalles) con encontrado y
 *    estado_encontrado; si difiere del estado_fisico del bien, el bien se
 *    actualiza y el BienObserver deja la bitácora "Revisión de Inventario".
 *
 * Acceso: el auditor ve/edita solo sus auditorías; el administrador todas.
 */
class AuditoriasService
{
    public function __construct(
        private readonly BienesService $bienes,
    ) {}

    /**
     * Listado paginado. Admin: todas; profesor: solo las suyas.
     */
    public function listar(Usuario $actor, int $perPage = 20): LengthAwarePaginator
    {
        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }

        $query = Auditoria::query()
            ->with('auditor')
            ->withCount('detalles')
            ->orderByDesc('cve_auditoria');

        if (! $this->bienes->esAdmin($actor)) {
            $query->where('cve_auditor', $actor->cve_persona);
        }

        return $query->paginate($perPage);
    }

    /**
     * Creación de una auditoría. El actor queda como auditor.
     */
    public function crear(Usuario $actor, array $datos): Auditoria
    {
        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'Solo profesores y administradores pueden realizar auditorías.');
        }

        return Auditoria::query()->create([
            'fecha_auditoria' => now()->toDateString(),
            'cve_auditor' => $actor->cve_persona,
            'observaciones_generales' => $datos['observaciones_generales'] ?? null,
        ]);
    }

    /**
     * Detalle de una auditoría con sus bienes escaneados.
     * Acceso: auditor propietario o admin.
     */
    public function obtener(Usuario $actor, int $cveAuditoria): Auditoria
    {
        $auditoria = Auditoria::query()
            ->with(['auditor', 'detalles.bien'])
            ->withCount('detalles')
            ->findOrFail($cveAuditoria);

        if (! $this->bienes->esAdmin($actor) && $auditoria->cve_auditor !== $actor->cve_persona) {
            abort(403, 'No tienes acceso a esta auditoría.');
        }

        return $auditoria;
    }

    /**
     * Registro de un bien escaneado en la auditoría.
     * - El bien debe ser accesible por el actor (mismo filtro de propiedad).
     * - No puede repetirse el mismo bien en la auditoría.
     * - Si estado_encontrado difiere, sincroniza el estado_fisico del bien.
     */
    public function registrarDetalle(Usuario $actor, int $cveAuditoria, array $datos): AuditoriaDetalle
    {
        $auditoria = $this->obtener($actor, $cveAuditoria);
        $bien = $this->bienes->obtener($actor, (int) $datos['cve_bien']);

        $yaRegistrado = AuditoriaDetalle::query()
            ->where('cve_auditoria', $auditoria->getKey())
            ->where('cve_bien', $bien->getKey())
            ->exists();

        if ($yaRegistrado) {
            throw ValidationException::withMessages([
                'cve_bien' => 'El bien ya está registrado en esta auditoría.',
            ]);
        }

        return DB::transaction(function () use ($auditoria, $bien, $datos) {
            $detalle = AuditoriaDetalle::query()->create([
                'cve_auditoria' => $auditoria->getKey(),
                'cve_bien' => $bien->getKey(),
                'encontrado' => $datos['encontrado'] ?? true,
                'estado_encontrado' => $datos['estado_encontrado'] ?? null,
                'observaciones_evidencia' => $datos['observaciones_evidencia'] ?? null,
            ]);

            $this->sincronizarEstadoBien($bien, $datos['estado_encontrado'] ?? null);

            return $detalle->load('bien');
        });
    }

    /**
     * Edición de un detalle de la auditoría (p. ej. corregir el estado
     * encontrado tras revisar la evidencia).
     */
    public function actualizarDetalle(Usuario $actor, int $cveAuditoria, int $cveDetalle, array $datos): AuditoriaDetalle
    {
        $this->obtener($actor, $cveAuditoria);

        $detalle = AuditoriaDetalle::query()
            ->where('cve_auditoria', $cveAuditoria)
            ->findOrFail($cveDetalle);

        $bien = $this->bienes->obtener($actor, (int) $detalle->cve_bien);

        $detalle->fill([
            'encontrado' => array_key_exists('encontrado', $datos)
                ? $datos['encontrado']
                : $detalle->encontrado,
            'estado_encontrado' => array_key_exists('estado_encontrado', $datos)
                ? $datos['estado_encontrado']
                : $detalle->estado_encontrado,
            'observaciones_evidencia' => array_key_exists('observaciones_evidencia', $datos)
                ? $datos['observaciones_evidencia']
                : $detalle->observaciones_evidencia,
        ]);
        $detalle->save();

        $this->sincronizarEstadoBien($bien, $detalle->estado_encontrado);

        return $detalle->fresh('bien');
    }

    /**
     * Quita un bien de la auditoría (escaneo erróneo).
     */
    public function eliminarDetalle(Usuario $actor, int $cveAuditoria, int $cveDetalle): void
    {
        $this->obtener($actor, $cveAuditoria);

        $detalle = AuditoriaDetalle::query()
            ->where('cve_auditoria', $cveAuditoria)
            ->findOrFail($cveDetalle);

        $detalle->delete();
    }

    /**
     * Si el estado encontrado difiere del estado físico actual, el bien se
     * actualiza y el BienObserver registra la bitácora
     * "Revisión de Inventario" (equivalente a trg_log_estado_fisico).
     */
    private function sincronizarEstadoBien(Bien $bien, ?string $estadoEncontrado): void
    {
        if ($estadoEncontrado === null || $estadoEncontrado === $bien->estado_fisico) {
            return;
        }

        $bien->estado_fisico = $estadoEncontrado;
        $bien->save();
    }
}
