<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versión resumida para los listados/historial: bien, solicitante y
 * encargado del bien (+ estado para las etiquetas de la UI). El detalle
 * completo se obtiene con GET /api/prestamos/{cve_prestamo} (PrestamoResource).
 */
class PrestamoResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_prestamo' => $this->cve_prestamo,
            'estado_prestamo' => $this->estado_prestamo,
            'bien' => $this->whenLoaded('bien', fn () => $this->bien ? [
                'cve_bien' => $this->bien->cve_bien,
                'identificador' => $this->bien->no_serie,
                'nombre' => $this->bien->nombre,
                'encargado' => $this->bien->relationLoaded('encargado') && $this->bien->encargado ? [
                    'cve_persona' => $this->bien->encargado->cve_persona,
                    'nombre' => trim($this->bien->encargado->nombre.' '.($this->bien->encargado->apellido_paterno ?? '')),
                ] : null,
            ] : null),
            'solicitante' => $this->whenLoaded('personaSolicita', fn () => $this->personaSolicita ? [
                'cve_persona' => $this->personaSolicita->cve_persona,
                'nombre' => trim($this->personaSolicita->nombre.' '.($this->personaSolicita->apellido_paterno ?? '')),
            ] : null),
        ];
    }
}
