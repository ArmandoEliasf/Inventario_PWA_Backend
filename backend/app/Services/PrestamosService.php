<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\Prestamo;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio del módulo de préstamos.
 *
 * Flujo de estados: pendiente → aprobado → por_devolver → devuelto.
 * - El encargado del bien (o un admin) acepta la solicitud (aprobado).
 * - El solicitante registra la devolución (por_devolver); un admin
 *   la registra directamente como devuelto.
 * - El encargado del bien (o un admin) confirma la devolución (devuelto).
 * - El estado del bien se sincroniza vía PrestamoObserver:
 *   aprobado → 'prestado'; devuelto → 'disponible'.
 */
class PrestamosService
{
    /** Vista: préstamos solicitados por el actor. */
    public const VISTA_SOLICITADOS = 'solicitados';

    /** Vista: préstamos sobre bienes donde el actor es encargado. */
    public const VISTA_RECIBIDOS = 'recibidos';

    public const VISTAS = [self::VISTA_SOLICITADOS, self::VISTA_RECIBIDOS];

    /** Estados de préstamo que mantienen el bien ocupado. */
    public const ESTADOS_ACTIVOS = ['pendiente', 'aprobado', 'por_devolver'];

    /**
     * Listado paginado según la vista.
     * - solicitados: préstamos pedidos por el actor (admin: todos).
     * - recibidos: préstamos de bienes donde el actor es encargado (admin: todos).
     */
    public function listar(Usuario $actor, string $vista, int $perPage = 20): LengthAwarePaginator
    {
        $query = Prestamo::query()
            ->with(['bien.encargado', 'personaSolicita'])
            ->orderByDesc('cve_prestamo');

        if ($this->esAdmin($actor)) {
            return $query->paginate($perPage);
        }

        if ($vista === self::VISTA_SOLICITADOS) {
            $query->where('cve_persona_solicita', $actor->cve_persona);
        } else {
            $query->whereHas('bien', fn ($q) => $q->where('cve_encargado', $actor->cve_persona));
        }

        return $query->paginate($perPage);
    }

    /**
     * Solicitud de préstamo. Reglas:
     * - El bien debe existir, estar activo (fuera de papelera) y disponible.
     * - No debe tener otro préstamo activo.
     * - Debe tener encargado y no ser del propio solicitante.
     * - Un admin queda aprobado automáticamente; los demás quedan pendientes.
     */
    public function solicitar(Usuario $actor, array $datos): Prestamo
    {
        $bien = Bien::query()->find($datos['cve_bien']);

        if ($bien === null) {
            abort(404, 'El bien no existe.');
        }

        if ($bien->activo === false) {
            abort(422, 'El bien está en la papelera.');
        }

        if ($bien->estado_prestamo !== 'disponible') {
            abort(422, 'El bien no está disponible para préstamo.');
        }

        if ($bien->cve_encargado === null) {
            abort(422, 'El bien no tiene encargado asignado.');
        }

        if ($bien->cve_encargado === $actor->cve_persona) {
            abort(422, 'No puedes solicitar un préstamo de tu propio bien.');
        }

        if ($this->tienePrestamoActivo($bien->getKey())) {
            abort(422, 'El bien ya tiene un préstamo en curso.');
        }

        $prestamo = new Prestamo([
            'cve_bien' => $bien->getKey(),
            'cve_persona_solicita' => $actor->cve_persona,
            'fecha_solicitud' => now(),
            'fecha_devolucion_pactada' => $datos['fecha_devolucion_pactada'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'estado_prestamo' => $this->esAdmin($actor) ? 'aprobado' : 'pendiente',
        ]);

        DB::transaction(function () use ($prestamo, $actor, $bien) {
            $prestamo->save();

            // el observer reacciona solo a updated(): un préstamo creado
            // directamente como aprobado (admin) marca el bien aquí
            if ($prestamo->estado_prestamo === 'aprobado') {
                Bien::query()
                    ->whereKey($bien->getKey())
                    ->update(['estado_prestamo' => 'prestado']);
            }

            $this->bitacora(
                $bien->getKey(),
                $actor,
                'Préstamo',
                $this->esAdmin($actor)
                    ? 'Préstamo auto-aprobado (solicitante administrador): '.$bien->nombre
                    : 'Solicitud de préstamo: '.$bien->nombre,
            );
        });

        return $prestamo->load(['bien.encargado', 'personaSolicita']);
    }

    /**
     * El encargado del bien (o un admin) acepta la solicitud:
     * pendiente → aprobado. El bien pasa a 'prestado' (observer).
     */
    public function aceptar(Usuario $actor, int $cvePrestamo): Prestamo
    {
        $prestamo = Prestamo::query()->with('bien')->findOrFail($cvePrestamo);

        if ($prestamo->estado_prestamo !== 'pendiente') {
            abort(422, 'Solo se pueden aceptar préstamos pendientes.');
        }

        $bien = $prestamo->bien;

        if (! $this->esAdmin($actor) && $bien->cve_encargado !== $actor->cve_persona) {
            abort(403, 'Solo el encargado del bien puede aceptar la solicitud.');
        }

        DB::transaction(function () use ($prestamo, $actor, $bien) {
            $prestamo->estado_prestamo = 'aprobado';
            $prestamo->save();

            $this->bitacora($bien->getKey(), $actor, 'Préstamo', 'Préstamo aprobado: '.$bien->nombre);
        });

        return $prestamo->fresh(['bien.encargado', 'personaSolicita']);
    }

    /**
     * El solicitante registra la devolución del bien:
     * aprobado → por_devolver. Un admin la registra directamente como
     * devuelto (bien vuelve a 'disponible' vía observer).
     */
    public function devolver(Usuario $actor, int $cvePrestamo): Prestamo
    {
        $prestamo = Prestamo::query()->with('bien')->findOrFail($cvePrestamo);

        if ($prestamo->estado_prestamo !== 'aprobado') {
            abort(422, 'Solo se pueden devolver préstamos aprobados.');
        }

        if ($prestamo->cve_persona_solicita !== $actor->cve_persona) {
            abort(403, 'Solo el solicitante puede registrar la devolución.');
        }

        $devueltoDirecto = $this->esAdmin($actor);

        DB::transaction(function () use ($prestamo, $actor, $devueltoDirecto) {
            $bien = $prestamo->bien;

            $prestamo->estado_prestamo = $devueltoDirecto ? 'devuelto' : 'por_devolver';
            $prestamo->fecha_devolucion_real = now();
            $prestamo->save();

            $this->bitacora(
                $bien->getKey(),
                $actor,
                'Devolución',
                $devueltoDirecto
                    ? 'Devolución directa (administrador): '.$bien->nombre
                    : 'Devolución registrada, pendiente de confirmación: '.$bien->nombre,
            );
        });

        return $prestamo->fresh(['bien.encargado', 'personaSolicita']);
    }

    /**
     * El encargado del bien (o un admin) confirma que el bien regresó:
     * por_devolver → devuelto. El bien vuelve a 'disponible' (observer).
     */
    public function confirmarDevolucion(Usuario $actor, int $cvePrestamo): Prestamo
    {
        $prestamo = Prestamo::query()->with('bien')->findOrFail($cvePrestamo);

        if ($prestamo->estado_prestamo !== 'por_devolver') {
            abort(422, 'Solo se pueden confirmar devoluciones registradas.');
        }

        $bien = $prestamo->bien;

        if (! $this->esAdmin($actor) && $bien->cve_encargado !== $actor->cve_persona) {
            abort(403, 'Solo el encargado del bien puede confirmar la devolución.');
        }

        DB::transaction(function () use ($prestamo, $actor, $bien) {
            $prestamo->estado_prestamo = 'devuelto';
            $prestamo->save();

            $this->bitacora($bien->getKey(), $actor, 'Devolución', 'Devolución confirmada: '.$bien->nombre);
        });

        return $prestamo->fresh(['bien.encargado', 'personaSolicita']);
    }

    /**
     * ¿El bien tiene un préstamo pendiente/aprobado/por_devolver?
     */
    public function tienePrestamoActivo(int $cveBien): bool
    {
        return Prestamo::query()
            ->where('cve_bien', $cveBien)
            ->whereIn('estado_prestamo', self::ESTADOS_ACTIVOS)
            ->exists();
    }

    private function esAdmin(Usuario $actor): bool
    {
        return $this->rolDe($actor) === BienesService::ROL_ADMIN;
    }

    private function rolDe(Usuario $actor): ?string
    {
        return $actor->role->nombre ?? null;
    }

    /**
     * Registro en bitácora con el motivo indicado (por nombre).
     */
    private function bitacora(int $cveBien, Usuario $actor, string $motivo, string $observaciones): void
    {
        $cveMotivo = DB::table('motivo_de_movimiento')
            ->where('nombre_motivo', $motivo)
            ->value('cve_motivo');

        if ($cveMotivo === null) {
            return;
        }

        BitacoraMovimiento::query()->create([
            'cve_bien' => $cveBien,
            'cve_motivo' => $cveMotivo,
            'cve_persona_accion' => $actor->cve_persona,
            'fecha_movimiento' => now(),
            'observaciones' => $observaciones,
        ]);
    }
}
