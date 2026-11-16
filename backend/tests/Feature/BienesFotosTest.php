<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\Bien;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BienesFotosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $profesor;

    private Usuario $profesorAjeno;

    private Usuario $alumno;

    private int $articulo = 1;

    /** PNG válido de 1x1 (el entorno de tests no tiene la extensión GD). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->articulo = (int) Articulo::query()->value('cve_articulo');

        $this->admin = Usuario::query()->find(1);       // Administrador, persona 1
        $this->profesor = Usuario::query()->find(3);    // Profesor, persona 3
        $this->profesorAjeno = Usuario::query()->where('usuario', 'jorge.rivas')->first(); // Profesor, persona 16
        $this->alumno = Usuario::query()->find(4);      // Estudiante, persona 8

        Storage::fake('local');
    }

    private function crearPayload(?string $identificador = null): array
    {
        return [
            'nombre' => 'Proyector con foto de prueba',
            'cve_articulo' => $this->articulo,
            'identificador' => $identificador ?? $this->identificadorUnico(),
            'costo_unitario' => 1500.50,
        ];
    }

    private function fotoValida(string $nombre = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode(self::PNG_1X1));
    }

    // ---------------------------------------------------------------
    // CREACIÓN CON FOTO
    // ---------------------------------------------------------------

    public function test_admin_crea_bien_con_foto(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/bienes', $this->crearPayload() + [
                'foto' => $this->fotoValida(),
            ]);

        $respuesta->assertStatus(201);

        $bien = Bien::query()->latest('cve_bien')->first();

        $this->assertNotNull($bien->foto_drive_id);
        $this->assertMatchesRegularExpression('/^bienes\/foto-'.$bien->cve_bien.'-[0-9]+\.(png|jpg|jpeg|webp)$/', $bien->foto_drive_id);
        Storage::disk('local')->assertExists($bien->foto_drive_id);
    }

    public function test_bien_puede_crear_sin_foto(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/bienes', $this->crearPayload());

        $respuesta->assertStatus(201);

        $bien = Bien::query()->latest('cve_bien')->first();

        $this->assertNull($bien->foto_drive_id);
        $this->assertNull($bien->foto_url);
    }

    public function test_foto_rechaza_formato_invalido(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/bienes', $this->crearPayload() + [
                'foto' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json']);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['foto']);
    }

    public function test_foto_rechaza_archivo_muy_pesado(): void
    {
        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/bienes', $this->crearPayload() + [
                'foto' => UploadedFile::fake()->create('foto.png', 6000, 'image/png'),
            ], ['Accept' => 'application/json']);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['foto']);
    }

    // ---------------------------------------------------------------
    // PROXY DE LECTURA
    // ---------------------------------------------------------------

    public function test_endpoint_proxy_sirve_la_foto(): void
    {
        $bien = $this->crearBienConFoto();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/bienes/'.$bien->cve_bien.'/foto');

        $respuesta->assertStatus(200);
        $this->assertStringContainsString('image/', (string) $respuesta->headers->get('Content-Type'));
        $this->assertNotSame('', $respuesta->getContent());
    }

    public function test_proxy_responde_404_sin_foto(): void
    {
        $bien = $this->crearBienSinFoto();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/bienes/'.$bien->cve_bien.'/foto');

        $respuesta->assertStatus(404);
    }

    public function test_proxy_requiere_token(): void
    {
        $bien = $this->crearBienConFoto();

        // actingAs del helper persiste en el guard; lo limpiamos para
        // simular un request sin token.
        $this->app['auth']->forgetGuards();

        $respuesta = $this->getJson('/api/bienes/'.$bien->cve_bien.'/foto');

        $respuesta->assertStatus(401);
    }

    public function test_alumno_no_puede_ver_foto(): void
    {
        $bien = $this->crearBienConFoto();

        $respuesta = $this->actingAs($this->alumno, 'sanctum')
            ->get('/api/bienes/'.$bien->cve_bien.'/foto');

        $respuesta->assertStatus(403);
    }

    public function test_profesor_ajeno_no_puede_ver_foto(): void
    {
        $bien = $this->crearBienConFoto();

        $respuesta = $this->actingAs($this->profesorAjeno, 'sanctum')
            ->get('/api/bienes/'.$bien->cve_bien.'/foto');

        $respuesta->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // REEMPLAZO
    // ---------------------------------------------------------------

    public function test_reemplazar_foto_borra_la_anterior(): void
    {
        $bien = $this->crearBienConFoto();
        $rutaOriginal = $bien->foto_drive_id;

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/bienes/'.$bien->cve_bien.'/foto', [
                'foto' => $this->fotoValida(),
            ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('message', 'Foto del bien guardada correctamente.');

        $bien->refresh();

        $this->assertNotSame($rutaOriginal, $bien->foto_drive_id);
        Storage::disk('local')->assertExists($bien->foto_drive_id);
        Storage::disk('local')->assertMissing($rutaOriginal);
    }

    public function test_profesor_encargado_puede_reemplazar_foto(): void
    {
        $bien = $this->crearBienConFoto($this->profesor);

        $respuesta = $this->actingAs($this->profesor, 'sanctum')
            ->post('/api/bienes/'.$bien->cve_bien.'/foto', [
                'foto' => $this->fotoValida(),
            ]);

        $respuesta->assertStatus(200);
    }

    public function test_profesor_ajeno_no_puede_reemplazar_foto(): void
    {
        $bien = $this->crearBienConFoto();

        $respuesta = $this->actingAs($this->profesorAjeno, 'sanctum')
            ->post('/api/bienes/'.$bien->cve_bien.'/foto', [
                'foto' => $this->fotoValida(),
            ]);

        $respuesta->assertStatus(403);
    }

    public function test_reemplazar_requiere_archivo(): void
    {
        $bien = $this->crearBienConFoto();

        $respuesta = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/bienes/'.$bien->cve_bien.'/foto', []);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['foto']);
    }

    // ---------------------------------------------------------------
    // PURGA
    // ---------------------------------------------------------------

    public function test_purgar_bien_borra_su_foto_del_disco(): void
    {
        $bien = $this->crearBienConFoto();
        $ruta = $bien->foto_drive_id;

        $this->actingAs($this->admin, 'sanctum')
            ->delete('/api/bienes/'.$bien->cve_bien); // papelera
        $this->actingAs($this->admin, 'sanctum')
            ->delete('/api/bienes/'.$bien->cve_bien.'/purgar');

        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_enviar_papelera_conserva_la_foto(): void
    {
        $bien = $this->crearBienConFoto();
        $ruta = $bien->foto_drive_id;

        $this->actingAs($this->admin, 'sanctum')
            ->delete('/api/bienes/'.$bien->cve_bien);

        Storage::disk('local')->assertExists($ruta);
    }

    // ---------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------

    private function crearBienConFoto(?Usuario $actor = null): Bien
    {
        $actor = $actor ?? $this->admin;

        $this->actingAs($actor, 'sanctum')
            ->post('/api/bienes', $this->crearPayload() + [
                'foto' => $this->fotoValida(),
            ]);

        return Bien::query()->latest('cve_bien')->first();
    }

    private function crearBienSinFoto(): Bien
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/bienes', $this->crearPayload());

        return Bien::query()->latest('cve_bien')->first();
    }

    private function identificadorUnico(): string
    {
        static $secuencia = 0;
        $secuencia++;

        return str_pad((string) (90000 + $secuencia), 7, '0', STR_PAD_LEFT);
    }
}
