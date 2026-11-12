<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versión resumida para listados: el detalle completo se obtiene con
 * GET /api/bienes/{cve_bien} (BienResource).
 */
class BienResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_bien' => $this->cve_bien,
            'identificador' => $this->no_serie,
            'nombre' => $this->nombre,
            'encargado' => $this->whenLoaded('encargado', fn () => $this->encargado ? [
                'cve_persona' => $this->encargado->cve_persona,
                'nombre' => trim($this->encargado->nombre.' '.($this->encargado->apellido_paterno ?? '')),
            ] : null),
            'estado_fisico' => $this->estado_fisico,
        ];
    }
}
