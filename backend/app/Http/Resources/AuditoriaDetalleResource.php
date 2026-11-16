<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización de un detalle de auditoría (bien escaneado).
 */
class AuditoriaDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_auditoria_det' => $this->cve_auditoria_det,
            'cve_auditoria' => $this->cve_auditoria,
            'encontrado' => (bool) $this->encontrado,
            'estado_encontrado' => $this->estado_encontrado,
            'observaciones_evidencia' => $this->observaciones_evidencia,
            'bien' => $this->whenLoaded('bien', fn () => $this->bien ? [
                'cve_bien' => $this->bien->cve_bien,
                'identificador' => $this->bien->no_serie,
                'nombre' => $this->bien->nombre,
                'estado_fisico' => $this->bien->estado_fisico,
                'activo' => (bool) $this->bien->activo,
            ] : null),
            'coincide_estado' => $this->whenLoaded('bien', fn () => $this->bien
                ? $this->estado_encontrado === $this->bien->estado_fisico
                : null),
        ];
    }
}
