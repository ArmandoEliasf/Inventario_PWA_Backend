<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización de un préstamo con el resumen del bien y el solicitante.
 */
class PrestamoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_prestamo' => $this->cve_prestamo,
            'estado_prestamo' => $this->estado_prestamo,
            'fecha_solicitud' => optional($this->fecha_solicitud)->toDateTimeString(),
            'fecha_devolucion_pactada' => optional($this->fecha_devolucion_pactada)->toDateTimeString(),
            'fecha_devolucion_real' => optional($this->fecha_devolucion_real)->toDateTimeString(),
            'observaciones' => $this->observaciones,
            'bien' => $this->whenLoaded('bien', fn () => $this->bien ? [
                'cve_bien' => $this->bien->cve_bien,
                'identificador' => $this->bien->no_serie,
                'nombre' => $this->bien->nombre,
                'estado_fisico' => $this->bien->estado_fisico,
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
