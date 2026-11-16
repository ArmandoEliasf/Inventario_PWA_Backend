<?php

namespace Tests\Feature;

use App\Models\AuditoriaDetalle;
use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriasTest extends TestCase
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
        return Bien::query()->where('cve_encargado', 3)->firstOrFail();
    }

    private function bienDeAdmin(): Bien
    {
        return Bien::query()->where('cve_encargado', 1)->firstOrFail();
    }

    // ---------------------------------------------------------------
    // CREACIÓN
    // ---------------------------------------------------------------

    public function test_profesor_crea_auditoria_y_queda_como_auditor(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [
                'observaciones_generales' => 'Auditoría semestral del lab',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Auditoría creada correctamente.')
            ->assertJsonPath('data.auditor.cve_persona', 3);

        $this->assertDatabaseHas('auditorias', [
            'cve_auditoria' => $respuesta->json('data.cve_auditoria'),
            'cve_auditor' => 3,
            'observaciones_generales' => 'Auditoría semestral del lab',
        ]);
    }

    public function test_alumno_no_puede_crear_auditoria(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // LISTADO Y DETALLE
    // ---------------------------------------------------------------

    public function test_profesor_ve_solo_sus_auditorias(): void
    {
        $propia = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data');

        $ajena = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data');

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/auditorias')
            ->assertStatus(200);

        $claves = collect($respuesta->json('data'))->pluck('cve_auditoria');

        $this->assertContains($propia['cve_auditoria'], $claves);
        $this->assertNotContains($ajena['cve_auditoria'], $claves);
    }

    public function test_admin_ve_todas_las_auditorias(): void
    {
        // el seeder demo ya trae 2 auditorías (cve_auditoria 1 y 2)
        $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', []);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/auditorias', []);

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/auditorias')
            ->assertStatus(200);

        $claves = collect($respuesta->json('data'))->pluck('cve_auditoria');

        $this->assertCount(4, $claves);
        $this->assertContains(1, $claves);
        $this->assertContains(2, $claves);
    }

    public function test_auditor_ve_detalle_con_detalles(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->assertStatus(201);

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/auditorias/{$cveAuditoria}")
            ->assertStatus(200);

        $this->assertSame(1, $respuesta->json('data.total_detalles'));
        $this->assertSame($bien->cve_bien, $respuesta->json('data.detalles.0.bien.cve_bien'));
    }

    public function test_ajeno_no_ve_detalle_de_auditoria(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        // el director no es admin ni auditor
        $this->actingAs($this->director, 'sanctum')
            ->getJson("/api/auditorias/{$cveAuditoria}")
            ->assertStatus(403);

        // el admin sí puede
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/auditorias/{$cveAuditoria}")
            ->assertStatus(200);
    }

    public function test_alumno_bloqueado_en_listado(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/auditorias')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // REGISTRO DE BIENES ESCANEADOS
    // ---------------------------------------------------------------

    public function test_registra_bien_escaneado_con_estado_diferente(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor(); // estado_fisico 'bueno' (seeder)
        $bien->estado_fisico = 'bueno';
        $bien->saveQuietly();

        $movimientosAntes = BitacoraMovimiento::query()->count();

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", [
                'cve_bien' => $bien->cve_bien,
                'encontrado' => true,
                'estado_encontrado' => 'regular',
                'observaciones_evidencia' => 'Presenta desgaste en el filtro',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Bien registrado en la auditoría.')
            ->assertJsonPath('data.bien.cve_bien', $bien->cve_bien)
            ->assertJsonPath('data.encontrado', true);

        // el estado del bien se sincronizó
        $this->assertSame('regular', $bien->fresh()->estado_fisico);

        // el BienObserver dejó la bitácora "Revisión de Inventario"
        $bitacora = BitacoraMovimiento::query()
            ->where('cve_bien', $bien->cve_bien)
            ->whereHas('motivo', fn ($q) => $q->where('nombre_motivo', 'Revisión de Inventario'))
            ->exists();

        $this->assertTrue($bitacora);
        $this->assertGreaterThan($movimientosAntes, BitacoraMovimiento::query()->count());

        // el detalle quedó en la auditoría
        $this->assertDatabaseHas('auditoria_detalle', [
            'cve_auditoria' => $cveAuditoria,
            'cve_bien' => $bien->cve_bien,
            'encontrado' => true,
            'estado_encontrado' => 'regular',
        ]);
    }

    public function test_registro_con_mismo_estado_no_bitacorea(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();
        $bien->estado_fisico = 'bueno';
        $bien->saveQuietly();

        $movimientosAntes = BitacoraMovimiento::query()->count();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", [
                'cve_bien' => $bien->cve_bien,
                'estado_encontrado' => 'bueno',
            ])
            ->assertStatus(201);

        $this->assertSame($movimientosAntes, BitacoraMovimiento::query()->count());
    }

    public function test_bien_duplicado_en_auditoria_falla(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->assertStatus(201);

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cve_bien');
    }

    public function test_profesor_no_registra_bien_ajeno(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bienAjeno = $this->bienDeAdmin();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bienAjeno->cve_bien])
            ->assertStatus(403);
    }

    public function test_alumno_no_registra_detalles(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $this->actingAs($this->alumno, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->assertStatus(403);
    }

    public function test_estado_encontrado_invalido_rechazado(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", [
                'cve_bien' => $bien->cve_bien,
                'estado_encontrado' => 'destruido',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado_encontrado');
    }

    // ---------------------------------------------------------------
    // EDICIÓN Y BORRADO DE DETALLES
    // ---------------------------------------------------------------

    public function test_actualiza_detalle_y_sincroniza_bien(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $detalle = $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->json('data');

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->putJson("/api/auditorias/{$cveAuditoria}/detalles/{$detalle['cve_auditoria_det']}", [
                'estado_encontrado' => 'deteriorado',
                'observaciones_evidencia' => 'Carcasa agrietada',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.estado_encontrado', 'deteriorado')
            ->assertJsonPath('data.coincide_estado', true);

        $this->assertSame('deteriorado', $bien->fresh()->estado_fisico);
    }

    public function test_ajeno_no_actualiza_detalle(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $detalle = $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->json('data');

        $this->actingAs($this->director, 'sanctum')
            ->putJson("/api/auditorias/{$cveAuditoria}/detalles/{$detalle['cve_auditoria_det']}", [
                'estado_encontrado' => 'regular',
            ])
            ->assertStatus(403);
    }

    public function test_elimina_detalle_de_auditoria(): void
    {
        $cveAuditoria = $this->actingAs($this->profesor, 'sanctum')
            ->postJson('/api/auditorias', [])
            ->json('data.cve_auditoria');

        $bien = $this->bienDeProfesor();

        $detalle = $this->actingAs($this->profesor, 'sanctum')
            ->postJson("/api/auditorias/{$cveAuditoria}/detalles", ['cve_bien' => $bien->cve_bien])
            ->json('data');

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/auditorias/{$cveAuditoria}/detalles/{$detalle['cve_auditoria_det']}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('auditoria_detalle', [
            'cve_auditoria_det' => $detalle['cve_auditoria_det'],
        ]);
    }

    public function test_sin_token_rechaza(): void
    {
        $this->getJson('/api/auditorias')->assertStatus(401);
    }
}
