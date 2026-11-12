<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\Prestamo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrestamosTest extends TestCase
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

    /**
     * Bien disponible encargado al profesor (rango demo 41-60; 41-44 están prestados).
     */
    private function bienDeProfesor(): Bien
    {
        return Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->firstOrFail();
    }

    private function bienDeAdmin(): Bien
    {
        return Bien::query()
            ->where('cve_encargado', 1)
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

    private function bienPrestado(): Bien
    {
        return Bien::query()->where('estado_prestamo', 'prestado')->firstOrFail();
    }

    // ---------------------------------------------------------------
    // SOLICITUD
    // ---------------------------------------------------------------

    public function test_alumno_solicita_bien_de_profesor_queda_pendiente(): void
    {
        $bien = $this->bienDeProfesor();

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/prestamos', [
                'cve_bien' => $bien->cve_bien,
                'fecha_devolucion_pactada' => '2026-11-20 17:00:00',
                'observaciones' => 'Para clase práctica',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.estado_prestamo', 'pendiente')
            ->assertJsonPath('data.bien.cve_bien', $bien->cve_bien)
            ->assertJsonPath('data.solicitante.cve_persona', $this->alumno->cve_persona);

        $this->assertDatabaseHas('prestamos', [
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'estado_prestamo' => 'pendiente',
        ]);

        // el bien sigue disponible hasta la aceptación
        $this->assertSame('disponible', $bien->fresh()->estado_prestamo);
    }

    public function test_admin_solicita_y_queda_auto_aprobado(): void
    {
        $bien = $this->bienDeProfesor();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(201)
            ->assertJsonPath('data.estado_prestamo', 'aprobado');

        // el bien pasa a prestado inmediatamente (observer)
        $this->assertSame('prestado', $bien->fresh()->estado_prestamo);
    }

    public function test_no_se_solicita_bien_prestado(): void
    {
        $bien = $this->bienPrestado();

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(422);
    }

    public function test_no_se_solicita_bien_en_papelera(): void
    {
        $bien = $this->bienDeAdmin();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(422);
    }

    public function test_encargado_no_solicita_su_propio_bien(): void
    {
        $bien = $this->bienDeProfesor();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(422);
    }

    public function test_no_acepta_segunda_solicitud_con_prestamo_activo(): void
    {
        $bien = $this->bienDeProfesor();

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(201);

        $this->actingAs($this->director, 'sanctum')
            ->postJson('/api/prestamos', ['cve_bien' => $bien->cve_bien])
            ->assertStatus(422);
    }

    public function test_solicitud_valida_campos(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/prestamos', [
                'cve_bien' => 999999,
                'fecha_devolucion_pactada' => 'no-es-fecha',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cve_bien', 'fecha_devolucion_pactada']);
    }

    // ---------------------------------------------------------------
    // ACEPTACIÓN
    // ---------------------------------------------------------------

    public function test_profesor_acepta_y_el_bien_pasa_a_prestado(): void
    {
        $bien = $this->bienDeProfesor();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $movimientos = BitacoraMovimiento::query()->count();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/aceptar")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'aprobado');

        $this->assertSame('prestado', $bien->fresh()->estado_prestamo);

        // bitácora con motivo "Préstamo"
        $bitacora = BitacoraMovimiento::query()
            ->where('cve_bien', $bien->cve_bien)
            ->whereHas('motivo', fn ($q) => $q->where('nombre_motivo', 'Préstamo'))
            ->latest('cve_movimiento')
            ->first();

        $this->assertNotNull($bitacora);
        $this->assertGreaterThan($movimientos, BitacoraMovimiento::query()->count());
    }

    public function test_alumno_no_puede_aceptar(): void
    {
        $bien = $this->bienDeProfesor();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/aceptar")
            ->assertStatus(403);
    }

    public function test_profesor_ajeno_no_acepta_prestamo_de_bien_de_director(): void
    {
        $bien = $this->bienDeDirector();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/aceptar")
            ->assertStatus(403);

        // el admin sí puede
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/aceptar")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'aprobado');
    }

    public function test_no_se_acepta_prestamo_no_pendiente(): void
    {
        $bien = $this->bienDeProfesor();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'aprobado',
        ]);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/aceptar")
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // DEVOLUCIÓN
    // ---------------------------------------------------------------

    private function prestamoAprobado(Bien $bien, Usuario $solicitante): Prestamo
    {
        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $solicitante->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'aprobado',
        ]);

        $bien->estado_prestamo = 'prestado';
        $bien->save();

        return $prestamo;
    }

    public function test_alumno_devuelve_queda_por_devolver_y_bien_sigue_prestado(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->prestamoAprobado($bien, $this->alumno);

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'por_devolver');

        $this->assertNotNull($respuesta->json('data.fecha_devolucion_real'));

        // físicamente el bien sigue prestado hasta la confirmación
        $this->assertSame('prestado', $bien->fresh()->estado_prestamo);
    }

    public function test_profesor_confirma_devolucion_y_bien_queda_disponible(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->prestamoAprobado($bien, $this->alumno);

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(200);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/confirmar-devolucion")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'devuelto');

        $this->assertSame('disponible', $bien->fresh()->estado_prestamo);

        $bitacora = BitacoraMovimiento::query()
            ->where('cve_bien', $bien->cve_bien)
            ->whereHas('motivo', fn ($q) => $q->where('nombre_motivo', 'Devolución'))
            ->exists();

        $this->assertTrue($bitacora);
    }

    public function test_admin_solicitante_devuelve_directo_como_devuelto(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->prestamoAprobado($bien, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'devuelto');

        // sin pasar por confirmación del encargado
        $this->assertSame('disponible', $bien->fresh()->estado_prestamo);
    }

    public function test_solo_solicitante_puede_devolver(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->prestamoAprobado($bien, $this->alumno);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(403);
    }

    public function test_devolver_requiere_estado_aprobado(): void
    {
        $bien = $this->bienDeProfesor();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(422);
    }

    public function test_confirmar_requiere_por_devolver(): void
    {
        $bien = $this->bienDeProfesor();
        $prestamo = $this->prestamoAprobado($bien, $this->alumno);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/confirmar-devolucion")
            ->assertStatus(422);
    }

    public function test_alumno_no_confirma_devolucion_ajena(): void
    {
        $bien = $this->bienDeDirector();
        $prestamo = $this->prestamoAprobado($bien, $this->alumno);

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/devolver")
            ->assertStatus(200);

        // el alumno no es encargado del bien: no confirma
        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/confirmar-devolucion")
            ->assertStatus(403);

        // el encargado (director) sí
        $this->actingAs($this->director, 'sanctum')
            ->postJson("/api/prestamos/{$prestamo->cve_prestamo}/confirmar-devolucion")
            ->assertStatus(200)
            ->assertJsonPath('data.estado_prestamo', 'devuelto');
    }

    // ---------------------------------------------------------------
    // LISTADOS
    // ---------------------------------------------------------------

    public function test_listado_solicitados_muestra_solo_mios(): void
    {
        $bienA = $this->bienDeProfesor();
        $bienB = $this->bienDeDirector();

        $propio = Prestamo::query()->create([
            'cve_bien' => $bienA->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        Prestamo::query()->create([
            'cve_bien' => $bienB->cve_bien,
            'cve_persona_solicita' => $this->profesor->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/prestamos?vista=solicitados')
            ->assertStatus(200);

        $solicitantes = collect($respuesta->json('data'))
            ->pluck('solicitante.cve_persona')
            ->unique();

        $this->assertSame([$this->alumno->cve_persona], $solicitantes->all());
        $this->assertContains($propio->cve_prestamo, collect($respuesta->json('data'))->pluck('cve_prestamo'));
    }

    public function test_listado_recibidos_profesor_ve_prestamos_de_sus_bienes(): void
    {
        $bien = $this->bienDeProfesor();

        $prestamo = Prestamo::query()->create([
            'cve_bien' => $bien->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        // el bien del director no debe aparecer para el profesor
        $bienDirector = $this->bienDeDirector();
        Prestamo::query()->create([
            'cve_bien' => $bienDirector->cve_bien,
            'cve_persona_solicita' => $this->alumno->cve_persona,
            'fecha_solicitud' => now(),
            'estado_prestamo' => 'pendiente',
        ]);

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/prestamos?vista=recibidos')
            ->assertStatus(200);

        $bienes = collect($respuesta->json('data'))->pluck('bien.cve_bien')->unique();

        $this->assertContains($bien->cve_bien, $bienes);
        $this->assertNotContains($bienDirector->cve_bien, $bienes);

        // el admin ve todos
        $admin = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/prestamos?vista=recibidos')
            ->assertStatus(200);

        $this->assertContains($bienDirector->cve_bien, collect($admin->json('data'))->pluck('bien.cve_bien'));
    }

    public function test_vista_invalida_rechazada(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/prestamos?vista=inexistente')
            ->assertStatus(422);
    }
}
