<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos y datos auxiliares para que la PWA rellene formularios
 * automáticamente (selects en cascada de edificio -> aula, tipo ->
 * familia -> artículo, marcas, modelos, personal, etc.) sin que el
 * usuario tenga que escribir nada.
 *
 * Reglas de acceso:
 * - Catálogos puros (edificios, tipos, familias, artículos, marcas,
 *   modelos) y datos de organización (aulas, profesores, personal,
 *   QR de un bien): cualquier usuario autenticado.
 * - Listas de bienes: Estudiante bloqueado (403); Profesor limitado a
 *   sus bienes (cve_encargado); Administrador acceso total. La
 *   excepción es "bienes disponibles", que cualquier usuario necesita
 *   para solicitar un préstamo.
 */
class InformacionService
{
    public function __construct(
        private readonly BienesService $bienes,
    ) {}

    // ------------------------------------------------------------------
    // Aulas y organización
    // ------------------------------------------------------------------

    /**
     * Aulas (id y nombre) de un edificio, para el select en cascada
     * edificio -> aula.
     */
    public function aulasPorEdificio(int $cveEdificio): array
    {
        return DB::table('aula')
            ->where('cve_edificio', $cveEdificio)
            ->orderBy('nombre')
            ->orderBy('cve_aula')
            ->get(['cve_aula', 'nombre'])
            ->all();
    }

    /**
     * Aulas con edificio, tipo, capacidad y adscripción.
     * Si el actor tiene una adscripción activa, solo ve las aulas de
     * ella (igual que el controller original).
     */
    public function aulas(Usuario $actor): array
    {
        $cveAdscripcion = DB::table('adscripcion_persona')
            ->where('cve_persona', $actor->cve_persona)
            ->where('activo', true)
            ->value('cve_adscripcion');

        return DB::table('aula as a')
            ->join('edificio as e', 'a.cve_edificio', '=', 'e.cve_edificio')
            ->join('tipo_aula as ta', 'a.cve_tipo_aula', '=', 'ta.cve_tipo_aula')
            ->leftJoin('adscripcion as ad', 'a.cve_adscripcion', '=', 'ad.cve_adscripcion')
            ->when($cveAdscripcion !== null, fn ($query) => $query->where('a.cve_adscripcion', $cveAdscripcion))
            ->orderBy('e.nombre')
            ->orderBy('a.nombre')
            ->get([
                'a.cve_aula',
                'a.nombre',
                'e.nombre as nombre_edificio',
                'ta.nombre as tipo_aula',
                'a.capacidad',
                DB::raw("COALESCE(ad.nombre_adscripcion, 'Sin asignar') as nombre_adscripcion"),
            ])
            ->all();
    }

    /**
     * Personal (id y nombre completo) de una carrera (área).
     */
    public function personalPorCarrera(int $cveCarrera): array
    {
        return DB::table('persona')
            ->where('cve_carrera', $cveCarrera)
            ->orderBy('nombre')
            ->orderBy('apellido_paterno')
            ->orderBy('cve_persona')
            ->get(['cve_persona', 'nombre', 'apellido_paterno', 'apellido_materno'])
            ->map(fn ($persona) => [
                'cve_persona' => $persona->cve_persona,
                'nombre_completo' => $this->nombreCompleto($persona),
            ])
            ->all();
    }

    /**
     * Listado de profesores activos (id y nombre completo) para
     * seleccionar encargado o solicitante.
     */
    public function profesores(): array
    {
        return DB::table('persona as p')
            ->join('profesor as prof', 'p.cve_persona', '=', 'prof.cve_persona')
            ->where('prof.activo', true)
            ->orderBy('p.nombre')
            ->orderBy('p.cve_persona')
            ->get(['p.cve_persona', 'p.nombre', 'p.apellido_paterno', 'p.apellido_materno'])
            ->map(fn ($profesor) => [
                'cve_persona' => $profesor->cve_persona,
                'nombre_completo' => $this->nombreCompleto($profesor),
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Bienes para selects
    // ------------------------------------------------------------------

    /**
     * Catálogo de bienes (id y nombre) para dropdowns. Con filtro
     * opcional por aula.
     * - Admin: todos los bienes activos.
     * - Profesor: solo los bienes donde es encargado.
     * - Estudiante: bloqueado.
     */
    public function bienesCatalogo(Usuario $actor, ?int $cveAula = null): array
    {
        $this->exigirConsulta($actor);

        return DB::table('bienes')
            ->where('activo', true)
            ->when(! $this->esAdmin($actor), fn ($query) => $query->where('cve_encargado', $actor->cve_persona))
            ->when($cveAula !== null, fn ($query) => $query->where('cve_aula', $cveAula))
            ->orderBy('nombre')
            ->orderBy('cve_bien')
            ->get(['cve_bien', 'nombre'])
            ->all();
    }

    /**
     * Bienes activos sin préstamo activo (estado_prestamo = disponible),
     * para el dropdown de solicitud de préstamos. Abierto a cualquier
     * usuario autenticado porque el Estudiante también solicita préstamos.
     */
    public function bienesDisponibles(?int $cveAula = null): array
    {
        return DB::table('bienes as b')
            ->leftJoin('modelos as m', 'b.cve_modelo', '=', 'm.cve_modelo')
            ->leftJoin('marcas as ma', 'm.cve_marca', '=', 'ma.cve_marca')
            ->leftJoin('persona as pe', 'b.cve_encargado', '=', 'pe.cve_persona')
            ->where('b.estado_prestamo', 'disponible')
            ->where('b.activo', true)
            ->when($cveAula !== null, fn ($query) => $query->where('b.cve_aula', $cveAula))
            ->orderBy('b.nombre')
            ->orderBy('b.cve_bien')
            ->get([
                'b.cve_bien',
                'b.no_serie',
                'b.nombre',
                'b.codigo_qr',
                'm.nombre_modelo',
                'ma.nombre_marca',
                'pe.nombre as nombre_encargado',
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Catálogos
    // ------------------------------------------------------------------

    public function tiposBien(): array
    {
        return DB::table('tipos_bien')
            ->orderBy('nombre')
            ->get(['cve_tipo', 'clave', 'nombre'])
            ->all();
    }

    public function familiasPorTipo(int $cveTipo): array
    {
        return DB::table('familias_articulos')
            ->where('cve_tipo', $cveTipo)
            ->orderBy('nombre')
            ->get(['cve_familia', 'clave', 'nombre', 'cve_tipo'])
            ->all();
    }

    public function articulosPorFamilia(int $cveFamilia): array
    {
        return DB::table('articulos')
            ->where('cve_familia', $cveFamilia)
            ->orderBy('nombre')
            ->get(['cve_articulo', 'clave', 'nombre', 'cve_familia'])
            ->all();
    }

    public function edificios(): array
    {
        return DB::table('edificio')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['cve_edificio', 'nombre', 'abreviatura'])
            ->all();
    }

    public function tiposAula(): array
    {
        return DB::table('tipo_aula')
            ->orderBy('nombre')
            ->get(['cve_tipo_aula', 'nombre'])
            ->all();
    }

    public function marcas(): array
    {
        return DB::table('marcas')
            ->orderBy('nombre_marca')
            ->get(['cve_marca', 'nombre_marca'])
            ->all();
    }

    public function modelos(): array
    {
        return DB::table('modelos')
            ->orderBy('nombre_modelo')
            ->get(['cve_modelo', 'nombre_modelo', 'cve_marca', 'descripcion'])
            ->all();
    }

    // ------------------------------------------------------------------
    // QR y valores por aula
    // ------------------------------------------------------------------

    /**
     * Código QR de un bien por su clave, con la URL completa del QR
     * impreso ({FRONTEND_URL}/bienes/{id}).
     */
    public function qrDeBien(int $cveBien): array
    {
        $bien = DB::table('bienes')
            ->where('cve_bien', $cveBien)
            ->first(['cve_bien', 'nombre', 'codigo_qr']);

        if ($bien === null) {
            abort(404, "No se encontró el bien con ID {$cveBien}.");
        }

        $base = rtrim((string) config('services.frontend.url'), '/');

        return [
            'cve_bien' => $bien->cve_bien,
            'nombre' => $bien->nombre,
            'codigo_qr' => $bien->codigo_qr,
            'qr_url' => $bien->codigo_qr !== null ? "{$base}/{$bien->codigo_qr}" : null,
        ];
    }

    /**
     * Bienes de un aula agrupados por nombre con su cantidad y el monto
     * monetario total del aula. Profesor: solo sus bienes del aula.
     */
    public function valorBienesCargo(Usuario $actor, int $cveAula): array
    {
        $this->exigirConsulta($actor);

        $bienes = DB::table('bienes')
            ->where('cve_aula', $cveAula)
            ->where('activo', true)
            ->when(! $this->esAdmin($actor), fn ($query) => $query->where('cve_encargado', $actor->cve_persona))
            ->groupBy('nombre')
            ->orderBy('nombre')
            ->select([
                'nombre as descripcion_bien',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('COALESCE(SUM(costo_unitario), 0) as costo_total'),
            ])
            ->get()
            ->map(fn ($fila) => [
                'descripcion_bien' => $fila->descripcion_bien,
                'cantidad' => (int) $fila->cantidad,
                'costo_total' => round((float) $fila->costo_total, 2),
            ])
            ->all();

        return [
            'bienes' => $bienes,
            'total_bienes' => array_sum(array_column($bienes, 'cantidad')),
            'monto_total' => round(array_sum(array_column($bienes, 'costo_total')), 2),
        ];
    }

    /**
     * Bienes individuales de un aula (sin agrupar) con nombre,
     * no_serie y costo unitario. Profesor: solo sus bienes del aula.
     */
    public function valorBienesCargoDetalle(Usuario $actor, int $cveAula): array
    {
        $this->exigirConsulta($actor);

        $bienes = DB::table('bienes')
            ->where('cve_aula', $cveAula)
            ->where('activo', true)
            ->when(! $this->esAdmin($actor), fn ($query) => $query->where('cve_encargado', $actor->cve_persona))
            ->orderBy('nombre')
            ->orderBy('cve_bien')
            ->get(['cve_bien', 'nombre', 'no_serie', 'costo_unitario', 'foto_drive_id'])
            ->map(fn ($bien) => [
                'cve_bien' => $bien->cve_bien,
                'descripcion_bien' => $bien->nombre,
                'no_serie' => $bien->no_serie,
                'costo_unitario' => round((float) $bien->costo_unitario, 2),
                'tiene_foto' => $bien->foto_drive_id !== null,
                'foto_url' => $bien->foto_drive_id !== null ? "/api/bienes/{$bien->cve_bien}/foto" : null,
            ])
            ->all();

        return [
            'bienes' => $bienes,
            'total_bienes' => count($bienes),
            'monto_total' => round(array_sum(array_column($bienes, 'costo_unitario')), 2),
        ];
    }

    /**
     * Bienes de un aula con marca, modelo, encargado, estados y foto.
     * Profesor: solo los bienes donde es encargado (modelo de propiedad
     * de este backend, en lugar del cve_profesor del aula original).
     */
    public function bienesPorAula(Usuario $actor, int $cveAula): array
    {
        $this->exigirConsulta($actor);

        return DB::table('bienes as b')
            ->leftJoin('modelos as m', 'b.cve_modelo', '=', 'm.cve_modelo')
            ->leftJoin('marcas as ma', 'm.cve_marca', '=', 'ma.cve_marca')
            ->leftJoin('persona as pe', 'b.cve_encargado', '=', 'pe.cve_persona')
            ->where('b.cve_aula', $cveAula)
            ->where('b.activo', true)
            ->when(! $this->esAdmin($actor), fn ($query) => $query->where('b.cve_encargado', $actor->cve_persona))
            ->orderBy('b.nombre')
            ->orderBy('b.cve_bien')
            ->get([
                'b.cve_bien',
                'b.nombre',
                'b.codigo_barras',
                'b.no_serie',
                'b.estado_fisico',
                'b.estado_prestamo',
                'b.foto_drive_id',
                'm.nombre_modelo as modelo',
                'ma.nombre_marca as marca',
                'pe.nombre as encargado_nombre',
                'pe.apellido_paterno as encargado_paterno',
                'pe.apellido_materno as encargado_materno',
            ])
            ->map(fn ($bien) => [
                'cve_bien' => $bien->cve_bien,
                'nombre' => $bien->nombre,
                'codigo_barras' => $bien->codigo_barras,
                'no_serie' => $bien->no_serie,
                'estado_fisico' => $bien->estado_fisico,
                'estado_prestamo' => $bien->estado_prestamo,
                'tiene_foto' => $bien->foto_drive_id !== null,
                'foto_url' => $bien->foto_drive_id !== null ? "/api/bienes/{$bien->cve_bien}/foto" : null,
                'modelo' => $bien->modelo,
                'marca' => $bien->marca,
                'nombre_encargado' => trim(implode(' ', array_filter([
                    $bien->encargado_nombre,
                    $bien->encargado_paterno,
                    $bien->encargado_materno,
                ]))) ?: null,
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function esAdmin(Usuario $actor): bool
    {
        return $this->bienes->esAdmin($actor);
    }

    private function exigirConsulta(Usuario $actor): void
    {
        if (! $this->bienes->puedeConsultar($actor)) {
            abort(403, 'El alumno no puede consultar el inventario.');
        }
    }

    private function nombreCompleto(object $persona): string
    {
        return trim(implode(' ', array_filter([
            $persona->nombre,
            $persona->apellido_paterno ?? null,
            $persona->apellido_materno ?? null,
        ])));
    }
}
