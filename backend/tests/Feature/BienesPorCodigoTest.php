<?php

namespace Tests\Feature;

use App\Models\Bien;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BienesPorCodigoTest extends TestCase
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

    private function bienDeProfesor(): Bien
    {
        return Bien::query()->where('cve_encargado', 3)->firstOrFail();
    }

    public function test_encuentra_bien_por_codigo_de_barras(): void
    {
        $bien = $this->bienDeProfesor();

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/bienes/por-codigo/{$bien->codigo_barras}")
            ->assertStatus(200);

        $this->assertSame($bien->cve_bien, $respuesta->json('data.cve_bien'));
        $this->assertSame($bien->nombre, $respuesta->json('data.nombre'));
        $this->assertArrayHasKey('qr_url', $respuesta->json());
    }

    public function test_encuentra_bien_por_identificador_no_serie(): void
    {
        $bien = $this->bienDeProfesor();

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/bienes/por-codigo/{$bien->no_serie}")
            ->assertStatus(200);

        $this->assertSame($bien->cve_bien, $respuesta->json('data.cve_bien'));
        $this->assertSame($bien->no_serie, $respuesta->json('data.identificador'));
    }

    public function test_encuentra_bien_por_url_qr_escaneada(): void
    {
        $bien = $this->bienDeProfesor();
        $urlQr = "http://localhost:5173/bienes/{$bien->cve_bien}";

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/bienes/por-codigo/'.rawurlencode($urlQr))
            ->assertStatus(200);

        $this->assertSame($bien->cve_bien, $respuesta->json('data.cve_bien'));
    }

    public function test_codigo_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->profesor, 'sanctum')
            ->getJson('/api/bienes/por-codigo/XX-YY-ZZ-9999999')
            ->assertStatus(404);
    }

    public function test_profesor_no_accede_a_bien_ajeno_por_codigo(): void
    {
        $bienAjeno = Bien::query()->where('cve_encargado', 1)->firstOrFail();

        $this->actingAs($this->profesor, 'sanctum')
            ->getJson("/api/bienes/por-codigo/{$bienAjeno->codigo_barras}")
            ->assertStatus(403);
    }

    public function test_admin_accede_a_cualquier_bien_por_codigo(): void
    {
        $bien = $this->bienDeProfesor();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/bienes/por-codigo/{$bien->codigo_barras}")
            ->assertStatus(200)
            ->assertJsonPath('data.cve_bien', $bien->cve_bien);
    }

    public function test_alumno_bloqueado(): void
    {
        $bien = $this->bienDeProfesor();

        $this->actingAs($this->alumno, 'sanctum')
            ->getJson("/api/bienes/por-codigo/{$bien->codigo_barras}")
            ->assertStatus(403);
    }

    public function test_sin_token_rechaza(): void
    {
        $bien = $this->bienDeProfesor();

        $this->getJson("/api/bienes/por-codigo/{$bien->codigo_barras}")
            ->assertStatus(401);
    }
}
