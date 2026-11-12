<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\MotivoMovimiento;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BienesService
{
    /** Roles con acceso total al inventario */
    public const ROL_ADMIN = 'Administrador';

    /** Rol profesor */
    public const ROL_PROFESOR = 'Profesor';

    /** Rol estudiante (alumno) */
    public const ROL_ALUMNO = 'Estudiante';

    /** Estados físicos permitidos */
    public const ESTADOS_FISICOS = ['bueno', 'regular', 'deteriorado'];

    public function __construct(
        private readonly CodigoBarrasService $codigoBarras,
    ) {}

    /**
     * Rol normalizado del usuario ('Administrador' | 'Profesor' | 'Estudiante' | null).
     */
    public function rolDe(Usuario $usuario): ?string
    {
        return $usuario->role->nombre ?? null;
    }

    public function esAdmin(Usuario $usuario): bool
    {
        return $this->rolDe($usuario) === self::ROL_ADMIN;
    }

    public function esProfesor(Usuario $usuario): bool
    {
        return $this->rolDe($usuario) === self::ROL_PROFESOR;
    }

    /**
     * ¿Puede consultar inventario? Solo profesor (suyos) y admin (todos).
     */
    public function puedeConsultar(Usuario $usuario): bool
    {
        return $this->esAdmin($usuario) || $this->esProfesor($usuario);
    }

    /**
     * Listado con filtro de propiedad según el rol del actor.
     */
    public function listar(Usuario $actor, int $perPage = 20): LengthAwarePaginator
    {
        $query = Bien::query()
            ->with(['articulo.familia.tipoBien', 'modelo.marca', 'aula', 'encargado'])
            ->orderByDesc('cve_bien');

        if (! $this->esAdmin($actor)) {
            $query->where('cve_encargado', $actor->cve_persona);
        }

        return $query->paginate($perPage);
    }

    /**
     * Detalle de un bien respetando la propiedad.
     */
    public function obtener(Usuario $actor, int $cveBien): Bien
    {
        $bien = Bien::query()
            ->with(['articulo.familia.tipoBien', 'modelo.marca', 'aula', 'encargado'])
            ->findOrFail($cveBien);

        if (! $this->esAdmin($actor) && $bien->cve_encargado !== $actor->cve_persona) {
            abort(403, 'No tienes acceso a este bien.');
        }

        return $bien;
    }

    /**
     * Creación de bienes.
     * - El profesor queda como encargado del bien creado.
     * - El admin puede asignar cualquier encargado (o ninguno).
     * - codigo_qr se genera automáticamente tras el insert.
     */
    public function crear(Usuario $actor, array $datos): Bien
    {
        if (! $this->esAdmin($actor) && ! $this->esProfesor($actor)) {
            abort(403, 'Solo profesores y administradores pueden crear bienes.');
        }

        $codigoBarras = $this->codigoBarras->construir(
            (int) $datos['cve_articulo'],
            $datos['identificador'],
        );

        if ($this->identificadorYaUsado($datos['identificador'])) {
            throw ValidationException::withMessages([
                'identificador' => 'El identificador ya está asignado a otro bien (no_serie).',
            ]);
        }

        $bien = new Bien([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'cve_articulo' => $datos['cve_articulo'],
            'cve_modelo' => $datos['cve_modelo'] ?? null,
            'cve_aula' => $datos['cve_aula'] ?? null,
            'costo_unitario' => $datos['costo_unitario'] ?? null,
            'no_serie' => $datos['identificador'], // el identificador llena el no_serie
            'no_factura' => $datos['no_factura'] ?? null,
            'estado_fisico' => $datos['estado_fisico'] ?? 'bueno',
            'estado_prestamo' => 'disponible',
            'activo' => true,
            'fecha_registro' => now()->toDateString(),
        ]);

        if ($this->esAdmin($actor) && ! empty($datos['cve_encargado'])) {
            $bien->cve_encargado = $datos['cve_encargado'];
        } else {
            $bien->cve_encargado = $actor->cve_persona;
        }

        DB::transaction(function () use ($bien, $codigoBarras) {
            $bien->codigo_barras = $codigoBarras;
            $bien->save();
            $bien->codigo_qr = 'bienes/'.$bien->getKey();
            $bien->saveQuietly();
        });

        return $bien->load(['articulo.familia.tipoBien', 'modelo.marca', 'aula', 'encargado']);
    }

    /**
     * Edición de bienes. El profesor solo puede editar bienes donde es encargado.
     * Si cambia el artículo, se re-genera el prefijo del código de barras
     * manteniendo el identificador.
     */
    public function editar(Usuario $actor, int $cveBien, array $datos): Bien
    {
        $bien = $this->obtener($actor, $cveBien);

        if ($bien->activo === false && ! $this->esAdmin($actor)) {
            abort(403, 'El bien está en la papelera.');
        }

        $identificador = $datos['identificador'] ?? $bien->no_serie;
        $cveArticulo = $datos['cve_articulo'] ?? $bien->cve_articulo;

        $codigoBarras = $this->codigoBarras->construir((int) $cveArticulo, $identificador);

        if ($this->identificadorYaUsado($identificador, $cveBien)) {
            throw ValidationException::withMessages([
                'identificador' => 'El identificador ya está asignado a otro bien (no_serie).',
            ]);
        }

        $bien->codigo_barras = $codigoBarras;
        $bien->no_serie = $identificador; // sincronizado con el identificador

        $campos = ['nombre', 'descripcion', 'cve_modelo', 'cve_aula', 'costo_unitario', 'no_factura', 'estado_fisico', 'cve_articulo'];
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $bien->{$campo} = $datos[$campo];
            }
        }

        if ($this->esAdmin($actor) && array_key_exists('cve_encargado', $datos)) {
            $bien->cve_encargado = $datos['cve_encargado'];
        }

        $bien->save();

        return $bien->fresh(['articulo.familia.tipoBien', 'modelo.marca', 'aula', 'encargado']);
    }

    /**
     * Enviar bien a papelera (activo = false).
     * Reglas: solo bienes disponibles (no prestados) y registra bitácora
     * con motivo "Baja Definitiva".
     */
    public function enviarPapelera(Usuario $actor, int $cveBien): Bien
    {
        $bien = $this->obtener($actor, $cveBien);

        if ($bien->activo === false) {
            abort(422, 'El bien ya está en la papelera.');
        }

        if ($bien->estado_prestamo !== 'disponible') {
            abort(422, 'El bien tiene un préstamo activo y no puede enviarse a la papelera.');
        }

        return DB::transaction(function () use ($bien, $actor) {
            $bien->activo = false;
            $bien->save();

            $motivo = MotivoMovimiento::query()
                ->where('nombre_motivo', 'Baja Definitiva')
                ->value('cve_motivo');

            if ($motivo !== null) {
                BitacoraMovimiento::query()->create([
                    'cve_bien' => $bien->getKey(),
                    'cve_motivo' => $motivo,
                    'cve_persona_accion' => $actor->cve_persona,
                    'fecha_movimiento' => now(),
                    'observaciones' => 'Bien enviado a papelera por '.($actor->nombre ?? $actor->usuario),
                ]);
            }

            return $bien->fresh();
        });
    }

    /**
     * ¿El identificador ya pertenece a otro bien? El identificador se
     * almacena en la columna no_serie (UNIQUE), por lo que la unicidad
     * es global, no por artículo.
     */
    private function identificadorYaUsado(string $identificador, ?int $excluirBien = null): bool
    {
        $query = Bien::query()->where('no_serie', $identificador);

        if ($excluirBien !== null) {
            $query->where('cve_bien', '!=', $excluirBien);
        }

        return $query->exists();
    }

    /**
     * URL completa del QR para la PWA (se configura vía FRONTEND_URL en .env).
     */
    public function urlQr(Bien $bien): string
    {
        $base = rtrim((string) config('services.frontend.url'), '/');

        return "{$base}/{$bien->codigo_qr}";
    }
}
