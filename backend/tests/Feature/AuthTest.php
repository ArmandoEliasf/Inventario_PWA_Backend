<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function credenciales(string $usuario = 'admin', string $contrasena = 'password'): array
    {
        return ['usuario' => $usuario, 'contrasena' => $contrasena];
    }

    public function test_login_devuelve_token_y_rol(): void
    {
        $respuesta = $this->postJson('/api/login', $this->credenciales());

        $respuesta->assertStatus(200)
            ->assertJsonStructure(['token', 'usuario' => ['cve_usuario', 'nombre', 'rol']]);

        $this->assertStringContainsString('|', $respuesta->json('token')); // formato Sanctum id|texto
        $this->assertSame('Administrador', $respuesta->json('usuario.rol'));
    }

    public function test_login_falla_con_contrasena_incorrecta(): void
    {
        $this->postJson('/api/login', $this->credenciales(contrasena: 'incorrecta'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('usuario');
    }

    public function test_login_falla_con_usuario_inexistente(): void
    {
        $this->postJson('/api/login', $this->credenciales(usuario: 'noexiste'))
            ->assertStatus(422);
    }

    public function test_login_falla_con_usuario_inactivo(): void
    {
        Usuario::query()->where('usuario', 'profesor')->update(['activo' => false]);

        $this->postJson('/api/login', $this->credenciales(usuario: 'profesor'))
            ->assertStatus(422);
    }

    public function test_token_puede_consumir_endpoints_protegidos(): void
    {
        $token = $this->postJson('/api/login', $this->credenciales())
            ->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('usuario.usuario', 'admin');
    }

    public function test_logout_revoca_el_token(): void
    {
        $token = $this->postJson('/api/login', $this->credenciales())
            ->json('token');

        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        // el token ya no existe en la BD
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_token_expira_pasada_la_ventana_configurada(): void
    {
        $token = $this->postJson('/api/login', $this->credenciales())
            ->json('token');

        // atrasa created_at mas alla de SANCTUM_TOKEN_EXPIRATION (480 min)
        DB::table('personal_access_tokens')
            ->where('tokenable_id', Usuario::where('usuario', 'admin')->value('cve_usuario'))
            ->update(['created_at' => now()->subMinutes(481)]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
