<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InformacionTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $profesor;

    private Usuario $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = Usuario::query()->find(1);    // Administrador, persona 1
        $this->profesor = Usuario::query()->find(3); // Profesor, persona 3
        $this->alumno = Usuario::query()->find(4);   // Estudiante, persona 8
    }

    // ------------------------------------------------------------------
    // Aulas
    // ------------------------------------------------------------------

    public function test_lista_aulas_de_un_edificio(): void
    {
        $edificio = DB::table('aula')->value('cve_edificio');

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/aulas/edificio/{$edificio}")
            ->assertStatus(200);

        $esperadas = DB::table('aula')->where('cve_edificio', $edificio)->count();
        $this->assertCount($esperadas, $respuesta->json('data'));
        $this->assertArrayHasKey('cve_aula', $respuesta->json('data.0'));
        $this->assertArrayHasKey('nombre', $respuesta->json('data.0'));
    }

    public function test_devuelve_array_vacio_si_el_edificio_no_tiene_aulas(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/aulas/edificio/99999')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_aulas_incluyen_edificio_tipo_y_adscripcion(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/informacion/aulas')
            ->assertStatus(200);

        $aulas = $respuesta->json('data');
        $this->assertNotEmpty($aulas);
        $this->assertArrayHasKey('nombre_edificio', $aulas[0]);
        $this->assertArrayHasKey('tipo_aula', $aulas[0]);
        $this->assertArrayHasKey('capacidad', $aulas[0]);
        $this->assertArrayHasKey('nombre_adscripcion', $aulas[0]);
    }

    public function test_aulas_se_filtran_por_adscripcion_del_actor(): void
    {
        // El profesor (persona 3, Carlos Sánchez) tiene adscripción 73113100 (DSM)
        $adscripcionProfesor = DB::table('adscripcion_persona')
            ->where('cve_persona', $this->profesor->cve_persona)
            ->where('activo', true)
            ->value('cve_adscripcion');
        $this->assertNotNull($adscripcionProfesor);

        // Se agrega un aula de otra adscripción que no debe aparecer
        $aulaOtraAdscripcion = DB::table('aula')->first();
        DB::table('aula')->insert([
            'cve_edificio' => $aulaOtraAdscripcion->cve_edificio,
            'cve_tipo_aula' => $aulaOtraAdscripcion->cve_tipo_aula,
            'nombre' => 'ZZ-OTRA',
        ]);

        $nombres = collect($this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/informacion/aulas')
            ->assertStatus(200)
            ->json('data'))->pluck('nombre');

        $this->assertNotContains('ZZ-OTRA', $nombres);
    }

    // ------------------------------------------------------------------
    // Personal y profesores
    // ------------------------------------------------------------------

    public function test_personal_por_carrera(): void
    {
        $cveArea = DB::table('area')->value('cve_area');
        DB::table('persona')->whereIn('cve_persona', [1, 3])->update(['cve_carrera' => $cveArea]);

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/informacion/personal/carrera/{$cveArea}")
            ->assertStatus(200);

        $cves = collect($respuesta->json('data'))->pluck('cve_persona');
        $this->assertContains(1, $cves);
        $this->assertContains(3, $cves);
        $this->assertNotContains(4, $cves);

        $juan = collect($respuesta->json('data'))->firstWhere('cve_persona', 1);
        $this->assertSame('Juan Pérez García', $juan['nombre_completo']);
    }

    public function test_lista_de_profesores(): void
    {
        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/informacion/profesores')
            ->assertStatus(200);

        $cves = collect($respuesta->json('data'))->pluck('cve_persona');
        $this->assertContains($this->profesor->cve_persona, $cves);

        $carlos = collect($respuesta->json('data'))->firstWhere('cve_persona', 3);
        $this->assertSame('Carlos Sánchez Rodríguez', $carlos['nombre_completo']);
    }

    // ------------------------------------------------------------------
    // Catálogo de bienes
    // ------------------------------------------------------------------

    public function test_estudiante_bloqueado_en_catalogo_de_bienes(): void
    {
        $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/informacion/bienes')
            ->assertStatus(403);
    }

    public function test_admin_ve_todo_el_catalogo_de_bienes(): void
    {
        $total = DB::table('bienes')->where('activo', true)->count();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/informacion/bienes')
            ->assertStatus(200);

        $this->assertCount($total, $respuesta->json('data'));
    }

    public function test_profesor_solo_ve_sus_bienes_en_el_catalogo(): void
    {
        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/informacion/bienes')
            ->assertStatus(200);

        $esperados = DB::table('bienes')
            ->where('activo', true)
            ->where('cve_encargado', $this->profesor->cve_persona)
            ->count();
        $this->assertCount($esperados, $respuesta->json('data'));

        $bienAjeno = DB::table('bienes')
            ->where('activo', true)
            ->where('cve_encargado', '!=', $this->profesor->cve_persona)
            ->first();
        if ($bienAjeno !== null) {
            $cves = collect($respuesta->json('data'))->pluck('cve_bien');
            $this->assertNotContains($bienAjeno->cve_bien, $cves);
        }
    }

    public function test_catalogo_de_bienes_filtrado_por_aula(): void
    {
        $bien = DB::table('bienes')->whereNotNull('cve_aula')->where('activo', true)->first();
        $esperados = DB::table('bienes')
            ->where('activo', true)
            ->where('cve_aula', $bien->cve_aula)
            ->where('cve_encargado', $this->admin->cve_persona)
            ->count();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/informacion/bienes?cve_aula={$bien->cve_aula}")
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Bienes disponibles
    // ------------------------------------------------------------------

    public function test_bienes_disponibles_excluye_prestados(): void
    {
        $bienPrestado = DB::table('bienes')->where('activo', true)->first();
        DB::table('bienes')->where('cve_bien', $bienPrestado->cve_bien)->update(['estado_prestamo' => 'prestado']);

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson('/api/informacion/bienes/disponibles')
            ->assertStatus(200);

        $cves = collect($respuesta->json('data'))->pluck('cve_bien');
        $this->assertNotContains($bienPrestado->cve_bien, $cves);

        $bienDisponible = DB::table('bienes')
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();
        $this->assertContains($bienDisponible->cve_bien, $cves);

        $item = collect($respuesta->json('data'))->firstWhere('cve_bien', $bienDisponible->cve_bien);
        $this->assertArrayHasKey('no_serie', $item);
        $this->assertArrayHasKey('nombre_modelo', $item);
        $this->assertArrayHasKey('nombre_marca', $item);
        $this->assertArrayHasKey('nombre_encargado', $item);
    }

    public function test_bienes_disponibles_filtrado_por_aula(): void
    {
        $aula = DB::table('aula')->value('cve_aula');

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/informacion/bienes/disponibles?cve_aula={$aula}")
            ->assertStatus(200);

        foreach ($respuesta->json('data') as $item) {
            $cveAula = DB::table('bienes')->where('cve_bien', $item['cve_bien'])->value('cve_aula');
            $this->assertSame($aula, $cveAula);
        }
    }

    // ------------------------------------------------------------------
    // Catálogos generales
    // ------------------------------------------------------------------

    public function test_catalogos_basicos(): void
    {
        $this->actingAs($this->alumno, 'sanctum');

        $this->getJson('/api/informacion/edificios')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['cve_edificio', 'nombre', 'abreviatura']]]);

        $this->getJson('/api/informacion/tipos-aula')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['cve_tipo_aula', 'nombre']]]);

        $this->getJson('/api/informacion/tipos-bien')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['cve_tipo', 'clave', 'nombre']]]);

        $this->getJson('/api/informacion/marcas')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['cve_marca', 'nombre_marca']]]);

        $this->getJson('/api/informacion/modelos')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['cve_modelo', 'nombre_modelo', 'cve_marca', 'descripcion']]]);
    }

    public function test_familias_por_tipo(): void
    {
        $tipo = DB::table('familias_articulos')->value('cve_tipo');

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/informacion/familias/tipo/{$tipo}")
            ->assertStatus(200);

        foreach ($respuesta->json('data') as $familia) {
            $this->assertSame($tipo, $familia['cve_tipo']);
        }
        $this->assertArrayHasKey('clave', $respuesta->json('data.0'));
    }

    public function test_articulos_por_familia(): void
    {
        $familia = DB::table('articulos')->value('cve_familia');

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/informacion/articulos/familia/{$familia}")
            ->assertStatus(200);

        foreach ($respuesta->json('data') as $articulo) {
            $this->assertSame($familia, $articulo['cve_familia']);
        }
        $this->assertArrayHasKey('clave', $respuesta->json('data.0'));
    }

    // ------------------------------------------------------------------
    // QR de un bien
    // ------------------------------------------------------------------

    public function test_qr_de_bien_por_id(): void
    {
        $bien = DB::table('bienes')->whereNotNull('codigo_qr')->first();

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/informacion/bien/qr/{$bien->cve_bien}")
            ->assertStatus(200);

        $this->assertSame($bien->cve_bien, $respuesta->json('data.cve_bien'));
        $this->assertSame($bien->codigo_qr, $respuesta->json('data.codigo_qr'));
        $this->assertStringEndsWith('/'.$bien->codigo_qr, $respuesta->json('data.qr_url'));
    }

    public function test_qr_de_bien_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/informacion/bien/qr/99999')
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Valor de bienes a cargo
    // ------------------------------------------------------------------

    public function test_estudiante_bloqueado_en_valor_bienes_cargo(): void
    {
        $aula = DB::table('aula')->value('cve_aula');

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/informacion/valor-bienes-cargo/{$aula}")
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/informacion/valor-bienes-cargo-detalle/{$aula}")
            ->assertStatus(403);

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/informacion/bienes-por-aula/{$aula}")
            ->assertStatus(403);
    }

    public function test_valor_bienes_cargo_del_admin_cubre_todos_los_bienes_del_aula(): void
    {
        $bien = DB::table('bienes')->whereNotNull('cve_aula')->where('activo', true)->first();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/informacion/valor-bienes-cargo/{$bien->cve_aula}")
            ->assertStatus(200);

        $esperados = DB::table('bienes')
            ->where('cve_aula', $bien->cve_aula)
            ->where('activo', true)
            ->count();

        $this->assertSame($esperados, $respuesta->json('data.total_bienes'));
        $this->assertGreaterThan(0, $respuesta->json('data.total_bienes'));

        foreach ($respuesta->json('data.bienes') as $fila) {
            $this->assertArrayHasKey('descripcion_bien', $fila);
            $this->assertArrayHasKey('cantidad', $fila);
            $this->assertArrayHasKey('costo_total', $fila);
        }
    }

    public function test_valor_bienes_cargo_del_profesor_solo_sus_bienes(): void
    {
        $bien = DB::table('bienes')
            ->where('cve_encargado', $this->profesor->cve_persona)
            ->whereNotNull('cve_aula')
            ->where('activo', true)
            ->first();

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/informacion/valor-bienes-cargo/{$bien->cve_aula}")
            ->assertStatus(200);

        $esperados = DB::table('bienes')
            ->where('cve_aula', $bien->cve_aula)
            ->where('activo', true)
            ->where('cve_encargado', $this->profesor->cve_persona)
            ->count();

        $this->assertSame($esperados, $respuesta->json('data.total_bienes'));
    }

    public function test_valor_bienes_cargo_detalle(): void
    {
        $bien = DB::table('bienes')->whereNotNull('cve_aula')->where('activo', true)->first();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/informacion/valor-bienes-cargo-detalle/{$bien->cve_aula}")
            ->assertStatus(200);

        $fila = $respuesta->json('data.bienes.0');
        $this->assertArrayHasKey('cve_bien', $fila);
        $this->assertArrayHasKey('descripcion_bien', $fila);
        $this->assertArrayHasKey('no_serie', $fila);
        $this->assertArrayHasKey('costo_unitario', $fila);
        $this->assertArrayHasKey('tiene_foto', $fila);
        $this->assertArrayHasKey('foto_url', $fila);

        $esperados = DB::table('bienes')
            ->where('cve_aula', $bien->cve_aula)
            ->where('activo', true)
            ->count();
        $this->assertSame($esperados, $respuesta->json('data.total_bienes'));
    }

    public function test_bienes_por_aula_del_admin(): void
    {
        $bien = DB::table('bienes')->whereNotNull('cve_aula')->where('activo', true)->first();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/informacion/bienes-por-aula/{$bien->cve_aula}")
            ->assertStatus(200);

        $esperados = DB::table('bienes')
            ->where('cve_aula', $bien->cve_aula)
            ->where('activo', true)
            ->count();
        $this->assertCount($esperados, $respuesta->json('data'));

        $fila = $respuesta->json('data.0');
        $this->assertArrayHasKey('codigo_barras', $fila);
        $this->assertArrayHasKey('estado_fisico', $fila);
        $this->assertArrayHasKey('estado_prestamo', $fila);
        $this->assertArrayHasKey('modelo', $fila);
        $this->assertArrayHasKey('marca', $fila);
        $this->assertArrayHasKey('nombre_encargado', $fila);
    }

    public function test_bienes_por_aula_del_profesor_solo_sus_bienes(): void
    {
        $bien = DB::table('bienes')
            ->where('cve_encargado', $this->profesor->cve_persona)
            ->whereNotNull('cve_aula')
            ->where('activo', true)
            ->first();

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/informacion/bienes-por-aula/{$bien->cve_aula}")
            ->assertStatus(200);

        foreach ($respuesta->json('data') as $fila) {
            $encargado = DB::table('bienes')->where('cve_bien', $fila['cve_bien'])->value('cve_encargado');
            $this->assertSame($this->profesor->cve_persona, $encargado);
        }
    }

    // ------------------------------------------------------------------
    // Autenticación
    // ------------------------------------------------------------------

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/informacion/aulas')->assertStatus(401);
        $this->getJson('/api/informacion/bienes/disponibles')->assertStatus(401);
        $this->getJson('/api/informacion/edificios')->assertStatus(401);
        $this->getJson('/api/aulas/edificio/1')->assertStatus(401);
    }

    public function test_parametros_de_filtro_validados(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/informacion/bienes?cve_aula=abc')
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/informacion/bienes/disponibles?cve_aula=99999')
            ->assertStatus(422);
    }
}
