<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Bien;
use App\Models\BitacoraMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BienesCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $admin = 1;       // cve_usuario admin (rol Administrador, persona 1)

    private int $profesor = 3;    // cve_usuario profesor (rol Profesor, persona 3)

    private int $alumno = 4;      // cve_usuario alumno (rol Estudiante, persona 8)

    private int $articulo = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->articulo = Articulo::query()->value('cve_articulo');
    }

    private function crearPayload(?int $articulo = null, string $identificador = '0000001'): array
    {
        return [
            'nombre' => 'Proyector Epson de prueba',
            'cve_articulo' => $articulo ?? $this->articulo,
            'identificador' => $identificador,
            'costo_unitario' => 1500.50,
        ];
    }

    // ---------------------------------------------------------------
    // CREACIÓN
    // ---------------------------------------------------------------

    public function test_admin_crea_bien_con_codigo_barras_y_qr(): void
    {
        $respuesta = $this->postJson('/api/bienes', $this->crearPayload(), ['X-Usuario' => $this->admin]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('message', 'Bien creado correctamente.');

        $bien = Bien::query()->latest('cve_bien')->first();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{1,2}-[A-Z0-9]{1,2}-[A-Z0-9]{1,4}-[0-9]{7}$/', $bien->codigo_barras);
        $this->assertSame('bienes/'.$bien->cve_bien, $bien->codigo_qr);
        $this->assertTrue($bien->activo);
        $this->assertSame('disponible', $bien->estado_prestamo);
        $this->assertSame('0000001', $bien->no_serie); // el identificador llena el no_serie
        $this->assertSame(1, $bien->cve_encargado); // encargado asignado por el admin (persona 1 default? no: admin persona 1)
    }

    public function test_profesor_crea_bien_y_queda_como_encargado(): void
    {
        $respuesta = $this->postJson('/api/bienes', $this->crearPayload(), ['X-Usuario' => $this->profesor]);

        $respuesta->assertStatus(201);

        $bien = Bien::query()->latest('cve_bien')->first();
        $this->assertSame(3, $bien->cve_encargado); // persona del profesor
    }

    public function test_alumno_no_puede_crear_bienes(): void
    {
        $this->postJson('/api/bienes', $this->crearPayload(), ['X-Usuario' => $this->alumno])
            ->assertStatus(403);
    }

    public function test_identificador_de_7_digitos_obligatorio(): void
    {
        $payload = $this->crearPayload(identificador: '123456'); // 6 dígitos

        $this->postJson('/api/bienes', $payload, ['X-Usuario' => $this->admin])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identificador');
    }

    public function test_identificador_con_ceros_iniciales_se_guarda_como_string(): void
    {
        $respuesta = $this->postJson('/api/bienes', $this->crearPayload(identificador: '0000001'), ['X-Usuario' => $this->admin]);

        $respuesta->assertStatus(201);

        $bien = Bien::query()->latest('cve_bien')->first();
        $this->assertStringEndsWith('-0000001', $bien->codigo_barras);
        $this->assertIsString($bien->codigo_barras);
    }

    public function test_identificador_duplicado_en_cualquier_articulo_falla(): void
    {
        // no_serie es UNIQUE: el identificador es único global, no por artículo
        $this->postJson('/api/bienes', $this->crearPayload(identificador: '0000001'), ['X-Usuario' => $this->admin])
            ->assertStatus(201);

        $otroArticulo = Articulo::query()->whereKeyNot($this->articulo)->first();

        $this->postJson('/api/bienes', $this->crearPayload(articulo: $otroArticulo->cve_articulo, identificador: '0000001'), ['X-Usuario' => $this->admin])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identificador');
    }

    // ---------------------------------------------------------------
    // CONSULTA
    // ---------------------------------------------------------------

    public function test_alumno_no_puede_consultar_inventario(): void
    {
        $this->getJson('/api/bienes', ['X-Usuario' => $this->alumno])
            ->assertStatus(403);

        $bien = Bien::query()->first();

        $this->getJson("/api/bienes/{$bien->cve_bien}", ['X-Usuario' => $this->alumno])
            ->assertStatus(403);
    }

    public function test_profesor_solo_ve_sus_bienes(): void
    {
        $respuesta = $this->getJson('/api/bienes', ['X-Usuario' => $this->profesor])
            ->assertStatus(200);

        $items = $respuesta->json('data');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame(3, $item['encargado']['cve_persona']);
        }
    }

    public function test_admin_ve_todos_los_bienes(): void
    {
        $this->getJson('/api/bienes', ['X-Usuario' => $this->admin])
            ->assertStatus(200)
            ->assertJsonCount(20, 'data'); // per_page por defecto
    }

    public function test_profesor_no_ve_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()->where('cve_encargado', 1)->first();

        $this->getJson("/api/bienes/{$bienAjeno->cve_bien}", ['X-Usuario' => $this->profesor])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // EDICIÓN
    // ---------------------------------------------------------------

    public function test_profesor_edita_su_propio_bien(): void
    {
        $bien = Bien::query()->where('cve_encargado', 3)->where('activo', true)->first();
        $articuloNuevo = Articulo::query()->whereKeyNot($bien->cve_articulo)->first();

        $respuesta = $this->putJson("/api/bienes/{$bien->cve_bien}", [
            'nombre' => 'Nombre editado',
            'estado_fisico' => 'regular',
            'cve_articulo' => $articuloNuevo->cve_articulo,
            'identificador' => '0000099',
        ], ['X-Usuario' => $this->profesor]);

        $respuesta->assertStatus(200);

        $bien->refresh();
        $this->assertSame('Nombre editado', $bien->nombre);
        $this->assertSame('regular', $bien->estado_fisico);
        $this->assertStringEndsWith('-0000099', $bien->codigo_barras);
        $this->assertSame('0000099', $bien->no_serie); // sincronizado
        $segmentos = explode('-', $bien->codigo_barras);
        $this->assertSame($articuloNuevo->clave, $segmentos[2]); // segmento artículo re-generado
    }

    public function test_profesor_no_edita_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()->where('cve_encargado', 1)->first();

        $this->putJson("/api/bienes/{$bienAjeno->cve_bien}", ['nombre' => 'hack'], ['X-Usuario' => $this->profesor])
            ->assertStatus(403);
    }

    public function test_admin_edita_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()->where('cve_encargado', 3)->first();

        $this->putJson("/api/bienes/{$bienAjeno->cve_bien}", ['nombre' => 'Editado por admin'], ['X-Usuario' => $this->admin])
            ->assertStatus(200);
    }

    // ---------------------------------------------------------------
    // PAPELERA
    // ---------------------------------------------------------------

    public function test_profesor_envia_su_bien_a_papelera(): void
    {
        $bien = Bien::query()
            ->where('cve_encargado', 3)
            ->where('activo', true)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $movimientosAntes = BitacoraMovimiento::count();

        $this->deleteJson("/api/bienes/{$bien->cve_bien}", [], ['X-Usuario' => $this->profesor])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Bien enviado a la papelera.');

        $bien->refresh();
        $this->assertFalse((bool) $bien->activo);

        $this->assertSame($movimientosAntes + 1, BitacoraMovimiento::count());
    }

    public function test_no_se_envia_a_papelera_bien_prestado(): void
    {
        $bien = Bien::query()
            ->where('estado_prestamo', 'prestado')
            ->where('cve_encargado', 1)
            ->first();

        $this->deleteJson("/api/bienes/{$bien->cve_bien}", [], ['X-Usuario' => $this->admin])
            ->assertStatus(422);
    }

    public function test_profesor_no_papeleriza_bien_ajeno(): void
    {
        $bienAjeno = Bien::query()
            ->where('cve_encargado', 1)
            ->where('estado_prestamo', 'disponible')
            ->first();

        $this->deleteJson("/api/bienes/{$bienAjeno->cve_bien}", [], ['X-Usuario' => $this->profesor])
            ->assertStatus(403);
    }

    public function test_sin_header_x_usuario_rechaza(): void
    {
        $this->getJson('/api/bienes')->assertStatus(401);
    }
}
