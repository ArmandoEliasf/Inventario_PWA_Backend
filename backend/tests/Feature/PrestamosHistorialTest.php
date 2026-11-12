<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Prestamo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrestamosHistorialTest extends TestCase
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

    private function bienDeProfesor(): Bien
    {
        return Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->firstOrFail();
    }

    private function bienDeDirector(): Bien
    {
        return Bien::query()
            ->where('cve_encargado', 2)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->firstOrFail();
    }

    private function crearPrestamo(Bien $bien, Usuario $solicitante, string $estado = 'pendiente'): Prestamo
    {
        return Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $solicitante->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => $estado,
            'observaciones' => 'prueba historial',
        ]);
    }

    // ---------------------------------------------------------------
    // LISTADO RESUMIDO (dos niveles de información)
    // ---------------------------------------------------------------

    public function test_listado_entrega_solo_resumen(): void
    {
        $bien = $this->bienDeProfesor();
        $this->crearPrestamo($bien, $this->alumno);

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/prestamos')
            ->assertStatus(200);

        $item = $respuesta->json('data.0');

        $this->assertSame(
            ['cve_prestamo', 'estado_prestamo', 'bien', 'solicitante'],
            array_keys($item),
        );

        $this->assertSame(
            ['cve_bien', 'identificador', 'nombre', 'encargado'],
            array_keys($item['bien']),
        );

        $this->assertSame(
            ['cve_persona', 'nombre'],
            array_keys($item['solicitante']),
        );
    }

    // ---------------------------------------------------------------
    // HISTORIALES POR ROL
    // ---------------------------------------------------------------

    public function test_admin_solicitados_muestra_solo_los_suyos(): void
    {
        $bienA = $this->bienDeProfesor();
        $bienB = $this->bienDeDirector();

        $propio = $this->crearPrestamo($bienA, $this->admin, 'aprobado');
        $this->crearPrestamo($bienB, $this->profesor, 'pendiente');

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/prestamos?vista=solicitados')
            ->assertStatus(200);

        $solicitantes = collect($respuesta->json('data'))
            ->pluck('solicitante.cve_persona')
            ->unique()
            ->values();

        $this->assertSame([$this->admin->cve_persona], $solicitantes->all());
        $this->assertContains($propio->cve_prestamo, collect($respuesta->json('data'))->pluck('cve_prestamo'));
    }

    public function test_alumno_ve_solo_su_historial(): void
    {
        $bienA = $this->bienDeProfesor();
        $bienB = $this->bienDeDirector();

        $this->crearPrestamo($bienA, $this->alumno, 'aprobado');
        $this->crearPrestamo($bienB, $this->profesor, 'pendiente');

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/prestamos?vista=solicitados')
            ->assertStatus(200);

        $solicitantes = collect($respuesta->json('data'))
            ->pluck('solicitante.cve_persona')
            ->unique()
            ->values();

        $this->assertSame([$this->alumno->cve_persona], $solicitantes->all());
    }

    public function test_historial_general_solo_admin(): void
    {
        $bienA = $this->bienDeProfesor();
        $bienB = $this->bienDeDirector();

        $this->crearPrestamo($bienA, $this->alumno, 'pendiente');
        $this->crearPrestamo($bienB, $this->profesor, 'aprobado');
        $this->crearPrestamo($bienA, $this->admin, 'aprobado');

        // el admin ve todos en la vista general
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/prestamos?vista=general')
            ->assertStatus(200);

        $solicitantes = collect($respuesta->json('data'))
            ->pluck('solicitante.cve_persona')
            ->unique()
            ->values();

        $this->assertCount(3, $solicitantes);
        $this->assertContains($this->alumno->cve_persona, $solicitantes);
        $this->assertContains($this->profesor->cve_persona, $solicitantes);
        $this->assertContains($this->admin->cve_persona, $solicitantes);

        // profesor y alumno no tienen acceso al general
        $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/prestamos?vista=general')
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/prestamos?vista=general')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // DETALLE COMPLETO (segundo nivel)
    // ---------------------------------------------------------------

    public function test_solicitante_ve_detalle_completo(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->crearPrestamo($bien, $this->alumno, 'aprobado');

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/prestamos/{$prestamo->cve_prestamo}")
            ->assertStatus(200)
            ->assertJsonPath('data.cve_prestamo', $prestamo->cve_prestamo);

        $data = $respuesta->json('data');

        // todo el historial del préstamo: fechas, observaciones, bien y solicitante
        foreach (['fecha_solicitud', 'fecha_devolucion_pactada', 'fecha_devolucion_real', 'observaciones'] as $campo) {
            $this->assertArrayHasKey($campo, $data);
        }

        $this->assertSame($bien->cve_bien, $data['bien']['cve_bien']);
        $this->assertSame($bien->no_serie, $data['bien']['identificador']);
        $this->assertSame($this->alumno->cve_persona, $data['solicitante']['cve_persona']);
        $this->assertSame(3, $data['bien']['encargado']['cve_persona']);
    }

    public function test_encargado_ve_detalle(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->crearPrestamo($bien, $this->alumno, 'aprobado');

        $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/prestamos/{$prestamo->cve_prestamo}")
            ->assertStatus(200);
    }

    public function test_admin_ve_detalle_de_cualquiera(): void
    {
        $bien = $this->bienDeDirector();
        $prestamo = $this->crearPrestamo($bien, $this->profesor, 'pendiente');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/prestamos/{$prestamo->cve_prestamo}")
            ->assertStatus(200)
            ->assertJsonPath('data.solicitante.cve_persona', $this->profesor->cve_persona);
    }

    public function test_ajeno_no_ve_detalle(): void
    {
        // préstamo del director sobre su bien: el alumno no es solicitante
        // ni encargado ni admin
        $bien = $this->bienDeDirector();
        $prestamo = $this->crearPrestamo($bien, $this->director, 'aprobado');

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/prestamos/{$prestamo->cve_prestamo}")
            ->assertStatus(403);

        // tampoco un profesor que no es encargado del bien
        $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/prestamos/{$prestamo->cve_prestamo}")
            ->assertStatus(403);
    }
}
