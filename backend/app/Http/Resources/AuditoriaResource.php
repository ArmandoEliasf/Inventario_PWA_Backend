<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización de una auditoría. En el listado (sin detalles cargados)
 * solo entrega el resumen con el total de bienes escaneados; el detalle
 * completo se obtiene con GET /api/auditorias/{cve_auditoria}.
 */
class AuditoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_auditoria' => $this->cve_auditoria,
            'fecha_auditoria' => $this->fecha_auditoria,
            'observaciones_generales' => $this->observaciones_generales,
            'auditor' => $this->whenLoaded('auditor', fn () => $this->auditor ? [
                'cve_persona' => $this->auditor->cve_persona,
                'nombre' => trim($this->auditor->nombre.' '.($this->auditor->apellido_paterno ?? '')),
            ] : null),
            'total_detalles' => $this->whenCounted('detalles'),
            'detalles' => $this->whenLoaded('detalles', fn () => AuditoriaDetalleResource::collection($this->detalles)),
        ];
    }
}
