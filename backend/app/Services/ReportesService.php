<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Datos crudos para los reportes que arma el frontend (resguardos,
 * movimientos internos, trazabilidad, bajas, mantenimiento y estadísticas).
 *
 * Reglas de acceso:
 * - Estudiante: bloqueado (403) en todos los reportes.
 * - Profesor: solo reportes de su propia persona/bienes.
 * - Administrador: cualquier persona/bien; opcionalmente puede filtrar
 *   los reportes generales por adscripción (?cve_adscripcion=).
 */
class ReportesService
{
    public function __construct(
        private readonly BienesService $bienes,
        private readonly PrestamosService $prestamos,
    ) {}

    // ------------------------------------------------------------------
    // Resguardo individual
    // ------------------------------------------------------------------

    /**
     * Bienes activos bajo responsabilidad de una persona:
     * - Bienes donde es encargado.
     * - Bienes con préstamo aprobado donde es la persona que solicita.
     * Filtro opcional por aula.
     */
    public function resguardoIndividual(Usuario $actor, int $cvePersona, ?int $cveAula = null): array
    {
        $this->exigirConsulta($actor);
        $this->exigirPersonaPermitida($actor, $cvePersona);

        return DB::table('bienes as b')
            ->leftJoin('modelos as m', 'b.cve_modelo', '=', 'm.cve_modelo')
            ->leftJoin('marcas as ma', 'm.cve_marca', '=', 'ma.cve_marca')
            ->leftJoin('aula as au', 'b.cve_aula', '=', 'au.cve_aula')
            ->leftJoin('prestamos as pres', function ($join) {
                $join->on('pres.cve_bien', '=', 'b.cve_bien')
                    ->where('pres.estado_prestamo', 'aprobado')
                    ->whereNull('pres.fecha_devolucion_real');
            })
            ->where('b.activo', true)
            ->where(function ($query) use ($cvePersona) {
                $query->where('b.cve_encargado', $cvePersona)
                    ->orWhere('pres.cve_persona_solicita', $cvePersona);
            })
            ->when($cveAula !== null, fn ($query) => $query->where('b.cve_aula', $cveAula))
            ->distinct()
            ->select([
                'b.cve_bien',
                'b.nombre as descripcion_bien',
                'm.nombre_modelo',
                'ma.nombre_marca',
                'b.no_serie',
                'b.costo_unitario',
                'b.no_factura',
                'b.codigo_barras as no_inventario',
                'b.estado_fisico',
                'au.cve_aula',
                'au.nombre as nombre_aula',
            ])
            ->orderBy('nombre_aula')
            ->orderBy('descripcion_bien')
            ->get()
            ->all();
    }

    // ------------------------------------------------------------------
    // Encabezados de reportes
    // ------------------------------------------------------------------

    /**
     * Datos dinámicos del encabezado: persona, puesto, área,
     * adscripción, folio SIIA y número de resguardo.
     */
    public function encabezadoReporte(Usuario $actor, int $cvePersona): array
    {
        $this->exigirConsulta($actor);
        $this->exigirPersonaPermitida($actor, $cvePersona);

        $persona = DB::table('persona')->where('cve_persona', $cvePersona)->first();

        if ($persona === null) {
            abort(404, 'Persona no encontrada.');
        }

        return $this->datosPersonaReporte($persona);
    }

    /**
     * Solo el nombre de la carrera (tabla area) para los encabezados
     * de los reportes de dirección.
     */
    public function encabezadoDireccion(Usuario $actor, int $cvePersona): array
    {
        $this->exigirConsulta($actor);
        $this->exigirPersonaPermitida($actor, $cvePersona);

        $persona = DB::table('persona')->where('cve_persona', $cvePersona)->first();

        if ($persona === null) {
            abort(404, 'Persona no encontrada o sin datos de carrera.');
        }

        $area = $persona->cve_carrera
            ? DB::table('area')->where('cve_area', $persona->cve_carrera)->first()
            : null;

        return [
            'nombre_area' => $area->nombre ?? 'N/A',
            'abreviatura' => $area->abreviatura ?? 'N/A',
        ];
    }

    // ------------------------------------------------------------------
    // Movimiento interno (formato por préstamo)
    // ------------------------------------------------------------------

    /**
     * Formato de Movimiento Interno: encabezado con quien entrega (baja),
     * quien recibe (alta) y la relación de bienes del movimiento.
     * El acceso es el mismo que el detalle del préstamo.
     */
    public function movimientoInterno(Usuario $actor, int $cvePrestamo): array
    {
        $this->exigirConsulta($actor);

        $prestamo = $this->prestamos->obtener($actor, $cvePrestamo); // 404 / 403 propios
        $bien = $prestamo->bien;

        $baja = $this->datosPersonaBaja($bien);
        $alta = $this->datosPersonaReporte(
            DB::table('persona')->where('cve_persona', $prestamo->cve_persona_solicita)->first(),
        );

        $bienDetalle = DB::table('bienes as b')
            ->leftJoin('modelos as m', 'b.cve_modelo', '=', 'm.cve_modelo')
            ->leftJoin('marcas as ma', 'm.cve_marca', '=', 'ma.cve_marca')
            ->leftJoin('aula as au', 'b.cve_aula', '=', 'au.cve_aula')
            ->leftJoin('edificio as ed', 'au.cve_edificio', '=', 'ed.cve_edificio')
            ->where('b.cve_bien', $bien->cve_bien)
            ->select([
                'b.cve_bien as no_inventario',
                'b.nombre as descripcion',
                'm.nombre_modelo as modelo',
                'b.no_serie',
                'ma.nombre_marca as marca',
                'b.costo_unitario as costo',
                'b.no_factura as factura',
                DB::raw("COALESCE(ed.nombre || ' - ' || au.nombre, au.nombre) as ubicacion"),
            ])
            ->first();

        return [
            'encabezado' => [
                'nombre_baja' => $baja['nombre_completo'],
                'area_baja' => $baja['area_departamento'],
                'adscripcion_baja' => $baja['adscripcion'],
                'siia_baja' => $baja['siia'],
                'resguardo_baja' => $baja['numero_resguardo'],

                'nombre_alta' => $alta['nombre_completo'],
                'area_alta' => $alta['area_departamento'],
                'adscripcion_alta' => $alta['adscripcion'],
                'siia_alta' => $alta['siia'],
                'resguardo_alta' => $alta['numero_resguardo'],

                'no_resguardo_movimiento' => $prestamo->cve_prestamo,
                'motivo_movimiento' => $prestamo->estado_prestamo,
                'observaciones_movimiento' => $prestamo->observaciones ?? 'N/A',
                'fecha_emision' => $prestamo->fecha_solicitud?->format('d/m/Y'),
            ],
            'relacion_bienes' => [$bienDetalle ? [
                'no_inventario' => $bienDetalle->no_inventario,
                'descripcion' => $bienDetalle->descripcion,
                'modelo' => $bienDetalle->modelo,
                'no_serie' => $bienDetalle->no_serie,
                'marca' => $bienDetalle->marca,
                'costo' => $bienDetalle->costo,
                'factura' => $bienDetalle->factura,
                'ubicacion' => $bienDetalle->ubicacion ?? 'N/A',
            ] : null],
        ];
    }

    // ------------------------------------------------------------------
    // Bienes dados de baja (papelera) y en mantenimiento
    // ------------------------------------------------------------------

    /**
     * Bienes dados de baja (activo = false).
     * Admin: todos (filtro opcional por adscripción); profesor: los suyos.
     */
    public function bienesBaja(Usuario $actor, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        return $this->consultaBienesConUbicacion($actor, $cveAdscripcion, false, null);
    }

    /**
     * Bienes activos con un estado físico dado (por defecto 'deteriorado',
     * equivalente a los bienes en mantenimiento; el estado 'reparacion'
     * del formato anterior no existe en este esquema).
     */
    public function bienesMantenimiento(Usuario $actor, string $estadoFisico, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        return $this->consultaBienesConUbicacion($actor, $cveAdscripcion, true, $estadoFisico);
    }

    // ------------------------------------------------------------------
    // Trazabilidad de un bien
    // ------------------------------------------------------------------

    /**
     * Historial completo del bien desde la bitácora de movimientos
     * (préstamos, devoluciones, cambios de estado, papelera...).
     */
    public function trazabilidad(Usuario $actor, int $cveBien): array
    {
        $this->exigirConsulta($actor);
        $bien = $this->bienes->obtener($actor, $cveBien); // 404 / 403 propiedad

        $historial = BitacoraMovimiento::query()
            ->with(['motivo', 'personaAccion'])
            ->where('cve_bien', $cveBien)
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('cve_movimiento')
            ->get()
            ->map(fn (BitacoraMovimiento $movimiento) => [
                'fecha' => $movimiento->fecha_movimiento?->toDateTimeString(),
                'tipo_movimiento' => $movimiento->motivo?->nombre_motivo ?? 'Movimiento',
                'responsable' => $movimiento->personaAccion
                    ? trim($movimiento->personaAccion->nombre.' '.($movimiento->personaAccion->apellido_paterno ?? ''))
                    : null,
                'observaciones' => $movimiento->observaciones,
            ])
            ->all();

        return [
            'bien' => [
                'cve_bien' => $bien->cve_bien,
                'nombre' => $bien->nombre,
                'no_serie' => $bien->no_serie,
                'aula_actual' => $bien->aula->nombre ?? null,
                'estado_fisico' => $bien->estado_fisico,
            ],
            'total_movimientos' => count($historial),
            'historial' => $historial,
        ];
    }

    // ------------------------------------------------------------------
    // Estadísticas
    // ------------------------------------------------------------------

    /**
     * Valor monetario acumulado por aula (bienes activos).
     */
    public function valorPorAula(Usuario $actor, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        return DB::table('bienes as b')
            ->join('aula as a', 'b.cve_aula', '=', 'a.cve_aula')
            ->where('b.activo', true)
            ->when(! $this->esAdmin($actor), fn ($query) => $query->where('b.cve_encargado', $actor->cve_persona))
            ->when($cveAdscripcion !== null, fn ($query) => $query->where('a.cve_adscripcion', $cveAdscripcion))
            ->groupBy('a.cve_aula', 'a.nombre')
            ->orderByDesc('valor_total')
            ->select([
                'a.cve_aula',
                'a.nombre as nombre_aula',
                DB::raw('COUNT(b.cve_bien) as total_bienes'),
                DB::raw('SUM(COALESCE(b.costo_unitario, 0)) as valor_total'),
            ])
            ->get()
            ->map(fn ($fila) => [
                'cve_aula' => $fila->cve_aula,
                'nombre_aula' => $fila->nombre_aula,
                'total_bienes' => (int) $fila->total_bienes,
                'valor_total' => (float) $fila->valor_total,
            ])
            ->all();
    }

    /**
     * Préstamos que no han sido devueltos (estados activos).
     */
    public function prestamosNoDevueltos(Usuario $actor, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        $esAdmin = $this->esAdmin($actor);

        return DB::table('prestamos as p')
            ->join('bienes as b', 'p.cve_bien', '=', 'b.cve_bien')
            ->join('persona as pe', 'p.cve_persona_solicita', '=', 'pe.cve_persona')
            ->leftJoin('aula as a', 'b.cve_aula', '=', 'a.cve_aula')
            ->leftJoin('edificio as e', 'a.cve_edificio', '=', 'e.cve_edificio')
            ->whereNull('p.fecha_devolucion_real')
            ->whereIn('p.estado_prestamo', PrestamosService::ESTADOS_ACTIVOS)
            ->when(! $esAdmin, fn ($query) => $query->where(function ($q) use ($actor) {
                $q->where('b.cve_encargado', $actor->cve_persona)
                    ->orWhere('p.cve_persona_solicita', $actor->cve_persona);
            }))
            ->when($cveAdscripcion !== null, fn ($query) => $query->where('a.cve_adscripcion', $cveAdscripcion))
            ->orderBy('p.fecha_devolucion_pactada')
            ->select([
                'p.cve_prestamo',
                'p.fecha_solicitud',
                'p.fecha_devolucion_pactada',
                'p.estado_prestamo',
                'b.cve_bien',
                'b.nombre as nombre_bien',
                'b.no_serie',
                'pe.nombre as persona_nombre',
                'pe.apellido_paterno',
                'pe.apellido_materno',
                'a.nombre as nombre_aula',
                'e.nombre as nombre_edificio',
            ])
            ->get()
            ->map(fn ($fila) => [
                'cve_prestamo' => $fila->cve_prestamo,
                'fecha_solicitud' => $this->fechaReporte($fila->fecha_solicitud),
                'fecha_devolucion_pactada' => $this->fechaReporte($fila->fecha_devolucion_pactada),
                'estado_prestamo' => $fila->estado_prestamo,
                'cve_bien' => $fila->cve_bien,
                'nombre_bien' => $fila->nombre_bien,
                'no_serie' => $fila->no_serie,
                'persona_nombre' => trim(implode(' ', array_filter([
                    $fila->persona_nombre,
                    $fila->apellido_paterno,
                    $fila->apellido_materno,
                ]))),
                'nombre_aula' => $fila->nombre_aula,
                'nombre_edificio' => $fila->nombre_edificio,
            ])
            ->all();
    }

    /**
     * Conteo de bienes activos por estado físico.
     */
    public function conteoEstadoFisico(Usuario $actor, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        return $this->conteoPorCampo($actor, $cveAdscripcion, 'estado_fisico');
    }

    /**
     * Conteo de bienes activos por estado de préstamo.
     */
    public function conteoEstadoPrestamo(Usuario $actor, ?int $cveAdscripcion = null): array
    {
        $this->exigirConsulta($actor);

        return $this->conteoPorCampo($actor, $cveAdscripcion, 'estado_prestamo');
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

    /**
     * Reportes por persona: admin cualquier persona, los demás solo la suya.
     */
    private function exigirPersonaPermitida(Usuario $actor, int $cvePersona): void
    {
        if (! $this->esAdmin($actor) && $actor->cve_persona !== $cvePersona) {
            abort(403, 'Solo puedes consultar reportes de tu propia persona.');
        }
    }

    /**
     * Datos de una persona para encabezados de reportes.
     */
    private function datosPersonaReporte(object $persona): array
    {
        $profesor = DB::table('profesor')->where('cve_persona', $persona->cve_persona)->first();

        $puesto = $profesor
            ? DB::table('tipo_profesor')->where('cve_tipo_profesor', $profesor->cve_tipo_profesor)->value('nombre')
            : null;

        // área: la del profesor si aplica; si no, la carrera de la persona
        $cveArea = $profesor->cve_area ?? $persona->cve_carrera ?? null;
        $area = $cveArea !== null
            ? DB::table('area')->where('cve_area', $cveArea)->first()
            : null;

        $adscripcion = DB::table('adscripcion_persona as ap')
            ->join('adscripcion as ads', 'ap.cve_adscripcion', '=', 'ads.cve_adscripcion')
            ->where('ap.cve_persona', $persona->cve_persona)
            ->where('ap.activo', true)
            ->orderBy('ap.cve_adscripcion_persona')
            ->select('ads.clave_adscripcion', 'ads.nombre_adscripcion')
            ->first();

        $siia = DB::table('folio_siia')
            ->where('cve_persona', $persona->cve_persona)
            ->where('estado', true)
            ->orderByDesc('cve_folio')
            ->value('clave_siia');

        $resguardo = DB::table('numero_resguardo')
            ->where('cve_persona', $persona->cve_persona)
            ->where('estado', true)
            ->orderByDesc('cve_resguardo_pk')
            ->value('no_resguardo');

        return [
            'cve_persona' => $persona->cve_persona,
            'nombre' => $persona->nombre,
            'apellido_paterno' => $persona->apellido_paterno,
            'apellido_materno' => $persona->apellido_materno,
            'nombre_completo' => trim(implode(' ', array_filter([
                $persona->nombre,
                $persona->apellido_paterno,
                $persona->apellido_materno,
            ]))),
            'puesto' => $puesto ?? 'Personal',
            'area_departamento' => $area->nombre ?? 'N/A',
            'area_abreviatura' => $area->abreviatura ?? 'N/A',
            'clave_adscripcion' => $adscripcion->clave_adscripcion ?? 'N/A',
            'adscripcion' => $adscripcion->nombre_adscripcion ?? 'N/A',
            'siia' => $siia ?? 'N/A',
            'numero_resguardo' => $resguardo ?? 'N/A',
            'fecha_generacion' => now()->toDateTimeString(),
        ];
    }

    /**
     * Quien entrega (baja) del movimiento interno: el encargado del bien;
     * si el bien no tiene encargado, el profesor responsable del aula.
     */
    private function datosPersonaBaja(Bien $bien): array
    {
        $cvePersona = $bien->cve_encargado;

        if ($cvePersona === null) {
            $cvePersona = DB::table('aula as au')
                ->join('profesor as pf', 'au.cve_profesor', '=', 'pf.cve_profesor')
                ->where('au.cve_aula', $bien->cve_aula)
                ->value('pf.cve_persona');
        }

        if ($cvePersona === null) {
            return [
                'nombre_completo' => 'N/A',
                'area_departamento' => 'N/A',
                'adscripcion' => 'N/A',
                'siia' => 'N/A',
                'numero_resguardo' => 'N/A',
            ];
        }

        return $this->datosPersonaReporte(
            DB::table('persona')->where('cve_persona', $cvePersona)->first(),
        );
    }

    /**
     * Listado de bienes con ubicación, usado por bajas y mantenimiento.
     */
    private function consultaBienesConUbicacion(Usuario $actor, ?int $cveAdscripcion, bool $activos, ?string $estadoFisico): array
    {
        $esAdmin = $this->esAdmin($actor);

        return DB::table('bienes as b')
            ->leftJoin('modelos as m', 'b.cve_modelo', '=', 'm.cve_modelo')
            ->leftJoin('marcas as ma', 'm.cve_marca', '=', 'ma.cve_marca')
            ->leftJoin('aula as au', 'b.cve_aula', '=', 'au.cve_aula')
            ->where('b.activo', $activos)
            ->when($estadoFisico !== null, fn ($query) => $query->where('b.estado_fisico', $estadoFisico))
            ->when(! $esAdmin, fn ($query) => $query->where('b.cve_encargado', $actor->cve_persona))
            ->when($cveAdscripcion !== null, fn ($query) => $query->where('au.cve_adscripcion', $cveAdscripcion))
            ->orderBy('b.nombre')
            ->select([
                'b.cve_bien',
                'b.nombre as descripcion_bien',
                'm.nombre_modelo',
                'ma.nombre_marca',
                'b.no_serie',
                'b.estado_fisico',
                'b.estado_prestamo',
                'au.nombre as ultima_ubicacion',
                'b.fecha_registro as fecha_alta',
                'b.descripcion as observaciones',
            ])
            ->get()
            ->all();
    }

    /**
     * Conteo de bienes activos agrupados por un campo.
     */
    private function conteoPorCampo(Usuario $actor, ?int $cveAdscripcion, string $campo): array
    {
        $esAdmin = $this->esAdmin($actor);

        return DB::table('bienes as b')
            ->leftJoin('aula as a', 'b.cve_aula', '=', 'a.cve_aula')
            ->where('b.activo', true)
            ->when(! $esAdmin, fn ($query) => $query->where('b.cve_encargado', $actor->cve_persona))
            ->when($cveAdscripcion !== null, fn ($query) => $query->where('a.cve_adscripcion', $cveAdscripcion))
            ->groupBy("b.{$campo}")
            ->orderByDesc('total')
            ->select("b.{$campo}", DB::raw('COUNT(*) as total'))
            ->get()
            ->map(fn ($fila) => [
                'valor' => $fila->{$campo},
                'total' => (int) $fila->total,
            ])
            ->all();
    }

    /**
     * Fecha formateada para los formatos impresos (d/m/Y), null-safe.
     */
    private function fechaReporte(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y');
    }
}
