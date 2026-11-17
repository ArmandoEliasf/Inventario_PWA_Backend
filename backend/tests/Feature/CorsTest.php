<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_preflight_permite_origen_de_la_pwa(): void
    {
        $this->optionsJson('/api/login', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
        ])
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_respuestas_incluyen_cabeceras_cors(): void
    {
        $this->getJson('/api/informacion/edificios', [
            'Origin' => 'http://localhost:5173',
        ])
            ->assertStatus(401)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_origen_desconocido_no_recibe_cabeceras_cors(): void
    {
        $this->getJson('/api/informacion/edificios', [
            'Origin' => 'https://sitio-maligno.example',
        ])
            ->assertStatus(401)
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
