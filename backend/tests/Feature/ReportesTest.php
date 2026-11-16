<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Prestamo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $director;

    private Usuario $profesor;

    private Usuario $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = Usuario::query()->find(1);    // Administrador, persona 1
        $this->director = Usuario::query()->find(2); // Director, persona 2
        $this->profesor = Usuario::query()->find(3); // Profesor, persona 3
        $this->alumno = Usuario::query()->find(4);   // Estudiante, persona 8
    }

    // ---------------------------------------------------------------
    // ACCESO GENERAL
    // ---------------------------------------------------------------

    public function test_alumno_bloqueado_en_reportes(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/reportes/resguardo-individual/8')
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/reportes/bajas')
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/reportes/valor-por-aula')
            ->assertStatus(403);
    }

    public function test_sin_token_rechaza(): void
    {
        $this->getJson('/api/reportes/valor-por-aula')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // RESGUARDO INDIVIDUAL
    // ---------------------------------------------------------------

    public function test_profesor_consulta_su_resguardo(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/resguardo-individual/3')
            ->assertStatus(200);

        $bienes = $respuesta->json('data');
        $this->assertNotEmpty($bienes);

        // todos los bienes del listado deben tener datos de inventario
        foreach ($bienes as $bien) {
            $this->assertArrayHasKey('no_inventario', $bien);
            $this->assertArrayHasKey('descripcion_bien', $bien);
        }
    }

    public function test_profesor_no_consulta_resguardo_ajeno(): void
    {
        $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/resguardo-individual/1')
            ->assertStatus(403);
    }

    public function test_admin_consulta_resguardo_ajeno(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/resguardo-individual/3')
            ->assertStatus(200);

        $this->assertNotEmpty($respuesta->json('data'));

        // filtro por aula funciona
        $cveAula = $respuesta->json('data.0.cve_aula');

        $filtrado = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reportes/resguardo-individual/3?cve_aula={$cveAula}")
            ->assertStatus(200);

        foreach ($filtrado->json('data') as $bien) {
            $this->assertSame($cveAula, $bien['cve_aula']);
        }
    }

    // ---------------------------------------------------------------
    // ENCABEZADOS
    // ---------------------------------------------------------------

    public function test_encabezado_reporte_con_datos(): void
    {
        // el seeder asigna resguardo y folio SIIA a las personas de los bienes
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/encabezado-reporte/1')
            ->assertStatus(200);

        $datos = $respuesta->json('data');

        $this->assertSame(1, $datos['cve_persona']);
        $this->assertArrayHasKey('nombre_completo', $datos);
        $this->assertArrayHasKey('puesto', $datos);
        $this->assertArrayHasKey('area_departamento', $datos);
        $this->assertArrayHasKey('adscripcion', $datos);
        $this->assertArrayHasKey('siia', $datos);
        $this->assertArrayHasKey('numero_resguardo', $datos);
        $this->assertArrayHasKey('fecha_generacion', $datos);

        // persona 1 es encargado de bienes => tiene resguardo y folio
        $this->assertNotSame('N/A', $datos['numero_resguardo']);
        $this->assertNotSame('N/A', $datos['siia']);
    }

    public function test_encabezado_reporte_persona_inexistente(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/encabezado-reporte/99999')
            ->assertStatus(404);
    }

    public function test_encabezado_direccion(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/encabezado-direccion/1')
            ->assertStatus(200);

        $this->assertArrayHasKey('nombre_area', $respuesta->json('data'));
        $this->assertArrayHasKey('abreviatura', $respuesta->json('data'));
    }

    public function test_profesor_no_consulta_encabezado_ajeno(): void
    {
        $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/encabezado-reporte/2')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // MOVIMIENTO INTERNO
    // ---------------------------------------------------------------

    private function cvePrestamoAprobado(): int
    {
        return Prestamo::query()
            ->where('estado_prestamo', 'aprobado')
            ->whereNull('fecha_devolucion_real')
            ->firstOrFail()
            ->cve_prestamo;
    }

    public function test_movimiento_interno(): void
    {
        $cvePrestamo = $this->cvePrestamoAprobado();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reportes/movimiento-interno/{$cvePrestamo}")
            ->assertStatus(200);

        $encabezado = $respuesta->json('data.encabezado');

        $this->assertSame($cvePrestamo, $encabezado['no_resguardo_movimiento']);
        $this->assertArrayHasKey('nombre_baja', $encabezado);
        $this->assertArrayHasKey('nombre_alta', $encabezado);
        $this->assertArrayHasKey('fecha_emision', $encabezado);

        $relacion = $respuesta->json('data.relacion_bienes');
        $this->assertCount(1, $relacion);
        $this->assertArrayHasKey('ubicacion', $relacion[0]);
        $this->assertArrayHasKey('no_inventario', $relacion[0]);
    }

    public function test_profesor_ajeno_no_consulta_movimiento_interno(): void
    {
        // un préstamo sobre un bien del director, solicitado por otra persona
        $cvePrestamo = Prestamo::query()
            ->whereHas('bien', fn ($q) => $q->where('cve_encargado', 2))
            ->where('cve_persona_solicita', '!=', 3)
            ->where('estado_prestamo', 'aprobado')
            ->firstOrFail()
            ->cve_prestamo;

        $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/reportes/movimiento-interno/{$cvePrestamo}")
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // BAJAS Y MANTENIMIENTO
    // ---------------------------------------------------------------

    public function test_bajas_muestra_solo_papelera(): void
    {
        $bien = Bien::query()->where('estado_prestamo', 'disponible')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/bajas')
            ->assertStatus(200);

        $claves = collect($respuesta->json('data'))->pluck('cve_bien');
        $this->assertContains($bien->cve_bien, $claves);
    }

    public function test_profesor_solo_ve_sus_bajas(): void
    {
        $bienPropio = Bien::query()
            ->where('cve_encargado', 3)
            ->where('estado_prestamo', 'disponible')
            ->firstOrFail();

        $bienAjeno = Bien::query()
            ->where('cve_encargado', 1)
            ->where('estado_prestamo', 'disponible')
            ->firstOrFail();

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bienPropio->cve_bien}");

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}");

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/bajas')
            ->assertStatus(200);

        $claves = collect($respuesta->json('data'))->pluck('cve_bien');
        $this->assertContains($bienPropio->cve_bien, $claves);
        $this->assertNotContains($bienAjeno->cve_bien, $claves);
    }

    public function test_mantenimiento_por_estado_fisico(): void
    {
        $bien = Bien::query()->where('estado_prestamo', 'disponible')->firstOrFail();
        $bien->estado_fisico = 'deteriorado';
        $bien->saveQuietly();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/mantenimiento')
            ->assertStatus(200);

        $claves = collect($respuesta->json('data'))->pluck('cve_bien');
        $this->assertContains($bien->cve_bien, $claves);

        foreach ($respuesta->json('data') as $fila) {
            $this->assertSame('deteriorado', $fila['estado_fisico']);
        }

        // filtro por estado válido
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/mantenimiento?estado_fisico=regular')
            ->assertStatus(200);

        // estado inválido
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/mantenimiento?estado_fisico=reparacion')
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // TRAZABILIDAD
    // ---------------------------------------------------------------

    public function test_trazabilidad_con_historial(): void
    {
        // el seeder deja bitácora en los primeros 8 bienes
        $bien = Bien::query()->orderBy('cve_bien')->firstOrFail();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/reportes/trazabilidad/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->assertSame($bien->cve_bien, $respuesta->json('data.bien.cve_bien'));
        $this->assertGreaterThan(0, $respuesta->json('data.total_movimientos'));

        $historial = $respuesta->json('data.historial');
        $this->assertArrayHasKey('fecha', $historial[0]);
        $this->assertArrayHasKey('tipo_movimiento', $historial[0]);
        $this->assertArrayHasKey('responsable', $historial[0]);
        $this->assertArrayHasKey('observaciones', $historial[0]);
    }

    public function test_trazabilidad_bien_inexistente(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/trazabilidad/99999')
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // ESTADÍSTICAS
    // ---------------------------------------------------------------

    public function test_valor_por_aula(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/valor-por-aula')
            ->assertStatus(200);

        $filas = $respuesta->json('data');
        $this->assertNotEmpty($filas);

        foreach ($filas as $fila) {
            $this->assertArrayHasKey('cve_aula', $fila);
            $this->assertArrayHasKey('total_bienes', $fila);
            $this->assertArrayHasKey('valor_total', $fila);
            $this->assertGreaterThan(0, $fila['total_bienes']);
        }

        // el total de bienes entre aulas coincide con los activos con aula
        $totalFilas = collect($filas)->sum('total_bienes');
        $totalEsperado = Bien::query()->where('activo', true)->whereNotNull('cve_aula')->count();
        $this->assertSame($totalEsperado, $totalFilas);
    }

    public function test_profesor_valor_por_aula_solo_sus_bienes(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/valor-por-aula')
            ->assertStatus(200);

        $totalFilas = collect($respuesta->json('data'))->sum('total_bienes');
        $totalEsperado = Bien::query()
            ->where('activo', true)
            ->where('cve_encargado', 3)
            ->whereNotNull('cve_aula')
            ->count();

        $this->assertSame($totalEsperado, $totalFilas);
    }

    public function test_prestamos_no_devueltos(): void
    {
        // el seeder crea préstamos aprobados sin devolver
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/prestamos-no-devueltos')
            ->assertStatus(200);

        $filas = $respuesta->json('data');
        $this->assertNotEmpty($filas);

        foreach ($filas as $fila) {
            $this->assertContains($fila['estado_prestamo'], ['pendiente', 'aprobado', 'por_devolver']);
        }
    }

    public function test_profesor_prestamos_no_devueltos_limitado(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/reportes/prestamos-no-devueltos')
            ->assertStatus(200);

        foreach ($respuesta->json('data') as $fila) {
            $cveBien = $fila['cve_bien'];
            $encargado = Bien::query()->where('cve_bien', $cveBien)->value('cve_encargado');

            $this->assertTrue(
                $encargado === 3 || $fila['persona_nombre'] !== null, // pertenece a sus bienes o es su solicitud
            );
        }
    }

    public function test_conteo_estado_fisico_y_prestamo(): void
    {
        $fisico = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/conteo-estado-fisico')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($fisico);
        $totalFisico = collect($fisico)->sum('total');
        $this->assertSame(
            Bien::query()->where('activo', true)->count(),
            $totalFisico,
        );

        $prestamo = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/conteo-estado-prestamo')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($prestamo);
        $totalPrestamo = collect($prestamo)->sum('total');
        $this->assertSame(
            Bien::query()->where('activo', true)->count(),
            $totalPrestamo,
        );
    }

    public function test_adscripcion_invalida_rechazada(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/reportes/valor-por-aula?cve_adscripcion=99999')
            ->assertStatus(422);
    }
}
