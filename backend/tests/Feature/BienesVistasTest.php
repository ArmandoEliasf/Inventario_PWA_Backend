<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BienesVistasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $profesor;

    private Usuario $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = Usuario::query()->find(1);    // rol Administrador, persona 1
        $this->profesor = Usuario::query()->find(3); // rol Profesor, persona 3
        $this->alumno = Usuario::query()->find(4);   // rol Estudiante, persona 8
    }

    // ---------------------------------------------------------------
    // LISTADO RESUMIDO
    // ---------------------------------------------------------------

    public function test_listado_entrega_solo_resumen(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/bienes')
            ->assertStatus(200);

        $item = $respuesta->json('data.0');

        $this->assertSame(
            ['cve_bien', 'identificador', 'nombre', 'encargado', 'estado_fisico'],
            array_keys($item),
        );
    }

    public function test_vista_invalida_rechazada(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/bienes?vista=inexistente')
            ->assertStatus(422)
            ->assertJsonValidationErrors('vista');
    }

    // ---------------------------------------------------------------
    // PAPELERA
    // ---------------------------------------------------------------

    public function test_vista_papelera_lista_solo_bienes_inactivos(): void
    {
        $bien = Bien::query()
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $general = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/bienes')
            ->assertStatus(200);

        $papelera = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/bienes?vista=papelera')
            ->assertStatus(200);

        $idsGeneral = array_column($general->json('data'), 'cve_bien');
        $idsPapelera = array_column($papelera->json('data'), 'cve_bien');

        $this->assertNotContains($bien->cve_bien, $idsGeneral);
        $this->assertContains($bien->cve_bien, $idsPapelera);
        $this->assertCount(1, $idsPapelera);
    }

    public function test_profesor_papelera_solo_suya(): void
    {
        // El profesor manda a papelera uno de sus bienes
        $bienPropio = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bienPropio->cve_bien}")
            ->assertStatus(200);

        // El admin manda a papelera un bien ajeno
        $bienAjeno = Bien::query()
            ->where('cve_encargado', 1)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}")
            ->assertStatus(200);

        $ids = array_column(
            $this->actingAs($this->profesor, 'sanctum')
                ->getJson('/api/bienes?vista=papelera')
                ->json('data'),
            'cve_bien',
        );

        $this->assertContains($bienPropio->cve_bien, $ids);
        $this->assertNotContains($bienAjeno->cve_bien, $ids);
    }

    // ---------------------------------------------------------------
    // FILTRO POR AULA Y EDIFICIO
    // ---------------------------------------------------------------

    public function test_filtro_por_aula(): void
    {
        $bien = Bien::query()->where('cve_encargado', 3)->where('activo', true)->first();
        $aula = $bien->aula;

        $items = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/bienes?cve_aula={$aula->cve_aula}")
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($items);

        $total = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('cve_aula', $aula->cve_aula)
            ->count();

        $this->assertCount($total, $items);

        foreach ($items as $item) {
            $bienItem = Bien::query()->find($item['cve_bien']);
            $this->assertSame($aula->cve_aula, $bienItem->cve_aula);
            $this->assertSame(3, $bienItem->cve_encargado);
        }
    }

    public function test_admin_filtro_por_edificio(): void
    {
        $bien = Bien::query()->where('activo', true)->first();
        $cveEdificio = $bien->aula->cve_edificio;

        $items = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/bienes?cve_edificio={$cveEdificio}")
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($items);

        $cveAulasDelEdificio = Aula::query()
            ->where('cve_edificio', $cveEdificio)
            ->pluck('cve_aula')
            ->all();

        foreach ($items as $item) {
            $bienItem = Bien::query()->find($item['cve_bien']);
            $this->assertContains($bienItem->cve_aula, $cveAulasDelEdificio);
        }
    }

    // ---------------------------------------------------------------
    // ENDPOINT DE AULAS (PESTAÑAS)
    // ---------------------------------------------------------------

    public function test_endpoint_aulas_profesor_solo_sus_aulas(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/aulas')
            ->assertStatus(200);

        $aulas = $respuesta->json('data');
        $this->assertNotEmpty($aulas);

        $bienesDelProfesor = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->get();

        // Todos los bienes del profesor caen en las aulas devueltas
        $cveAulas = array_column($aulas, 'cve_aula');
        foreach ($bienesDelProfesor as $bien) {
            $this->assertContains($bien->cve_aula, $cveAulas);
        }

        // Los conteos suman el total de bienes activos del profesor
        $totalConteos = array_sum(array_column($aulas, 'total_bienes'));
        $this->assertSame($bienesDelProfesor->count(), $totalConteos);

        foreach ($aulas as $aula) {
            $this->assertArrayHasKey('cve_aula', $aula);
            $this->assertArrayHasKey('nombre', $aula);
            $this->assertArrayHasKey('cve_edificio', $aula);
            $this->assertArrayHasKey('edificio', $aula);
        }
    }

    public function test_endpoint_aulas_admin_filtra_por_edificio(): void
    {
        $todas = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/aulas')
            ->assertStatus(200)
            ->json('data');

        $cveEdificio = $todas[0]['cve_edificio'];

        $filtradas = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/aulas?cve_edificio={$cveEdificio}")
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($filtradas);
        $this->assertLessThanOrEqual(count($todas), count($filtradas));

        foreach ($filtradas as $aula) {
            $this->assertSame($cveEdificio, $aula['cve_edificio']);
        }
    }

    public function test_alumno_bloqueado_en_aulas_y_papelera(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/aulas')
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/bienes?vista=papelera')
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // RESTAURAR Y PURGAR (ACCIONES DE LA PAPELERA)
    // ---------------------------------------------------------------

    public function test_admin_restaura_bien_de_papelera(): void
    {
        $bien = Bien::query()->where('activo', true)->where('estado_prestamo', 'disponible')->first();
        $movimientosAntes = BitacoraMovimiento::count();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/bienes/{$bien->cve_bien}/restaurar")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Bien restaurado desde la papelera.');

        $bien->refresh();
        $this->assertTrue($bien->activo);

        // Registra bitácora con motivo Restauración
        $bitacoras = BitacoraMovimiento::query()
            ->where('cve_bien', $bien->cve_bien)
            ->get();
        $this->assertSame($movimientosAntes + 2, BitacoraMovimiento::count());
        $this->assertTrue($bitacoras->contains(fn ($m) => $m->motivo?->nombre_motivo === 'Restauración'));
    }

    public function test_profesor_restaura_su_bien(): void
    {
        $bien = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->profesor, 'sanctum')
            ->putJson("/api/bienes/{$bien->cve_bien}/restaurar")
            ->assertStatus(200);

        $bien->refresh();
        $this->assertTrue($bien->activo);
    }

    public function test_no_se_restaura_bien_que_no_esta_en_papelera(): void
    {
        $bien = Bien::query()->where('activo', true)->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/bienes/{$bien->cve_bien}/restaurar")
            ->assertStatus(422);
    }

    public function test_profesor_no_restaura_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()->where('activo', true)->where('estado_prestamo', 'disponible')->first();

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}");
        // no llega: no es su bien

        $bienAjeno->refresh();
        $this->assertTrue($bienAjeno->activo);

        // Forzarlo a papelera como admin y verificar que el profesor no puede restaurarlo
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->profesor, 'sanctum')
            ->putJson("/api/bienes/{$bienAjeno->cve_bien}/restaurar")
            ->assertStatus(403);
    }

    public function test_alumno_no_restaura_ni_purga(): void
    {
        $bien = Bien::query()->where('activo', true)->where('estado_prestamo', 'disponible')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->alumno, 'sanctum')
            ->putJson("/api/bienes/{$bien->cve_bien}/restaurar")
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}/purgar")
            ->assertStatus(403);
    }

    public function test_admin_purga_bien_de_papelera(): void
    {
        $bien = Bien::query()->where('activo', true)->where('estado_prestamo', 'disponible')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $rastroAntes = BitacoraMovimiento::whereNull('cve_bien')->count();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}/purgar")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Bien eliminado definitivamente.');

        // El bien y sus dependencias ya no existen
        $this->assertNull(Bien::query()->find($bien->cve_bien));
        $this->assertDatabaseMissing('prestamos', ['cve_bien' => $bien->cve_bien]);
        $this->assertDatabaseMissing('bitacora_movimientos', ['cve_bien' => $bien->cve_bien]);

        // Queda rastro en bitácora (cve_bien null) con motivo Baja Definitiva
        $rastro = BitacoraMovimiento::query()
            ->whereNull('cve_bien')
            ->orderByDesc('cve_movimiento')
            ->first();
        $this->assertNotNull($rastro);
        $this->assertStringContainsString($bien->nombre, $rastro->observaciones);
        $this->assertStringContainsString($bien->codigo_barras, $rastro->observaciones);
    }

    public function test_no_se_purga_bien_activo(): void
    {
        $bien = Bien::query()->where('activo', true)->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}/purgar")
            ->assertStatus(422);

        $this->assertNotNull(Bien::query()->find($bien->cve_bien));
    }

    public function test_profesor_purga_su_bien(): void
    {
        $bien = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bien->cve_bien}/purgar")
            ->assertStatus(200);

        $this->assertNull(Bien::query()->find($bien->cve_bien));
    }

    public function test_profesor_no_purga_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()->where('activo', true)->where('estado_prestamo', 'disponible')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}")
            ->assertStatus(200);

        $this->actingAs($this->profesor, 'sanctum')
            ->deleteJson("/api/bienes/{$bienAjeno->cve_bien}/purgar")
            ->assertStatus(403);

        $this->assertNotNull(Bien::query()->find($bienAjeno->cve_bien));
    }

    // ---------------------------------------------------------------
    // DETALLE
    // ---------------------------------------------------------------

    public function test_detalle_incluye_foto_y_codigo_barras(): void
    {
        $bien = Bien::query()->where('activo', true)->first();
        $bien->foto_url = 'https://drive.example.com/foto.jpg';
        $bien->saveQuietly();

        $data = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/bienes/{$bien->cve_bien}")
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($bien->foto_url, $data['foto_url']);
        $this->assertSame($bien->codigo_barras, $data['codigo_barras']);
        $this->assertSame($bien->no_serie, $data['identificador']);
    }
}
