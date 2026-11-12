<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cve_bien' => $this->cve_bien,
            'nombre' => $this->nombre,
            'codigo_barras' => $this->codigo_barras,
            'codigo_qr' => $this->codigo_qr,
            'identificador' => $this->no_serie,
            'no_factura' => $this->no_factura,
            'descripcion' => $this->descripcion,
            'foto_url' => $this->foto_url,
            'foto_drive_id' => $this->foto_drive_id,
            'costo_unitario' => $this->costo_unitario,
            'estado_fisico' => $this->estado_fisico,
            'estado_prestamo' => $this->estado_prestamo,
            'activo' => (bool) $this->activo,
            'fecha_registro' => $this->fecha_registro,
            'articulo' => $this->whenLoaded('articulo', fn () => [
                'cve_articulo' => $this->articulo->cve_articulo,
                'clave' => $this->articulo->clave,
                'nombre' => $this->articulo->nombre,
                'familia' => $this->when($this->relationLoaded('articulo') && $this->articulo->relationLoaded('familia'), fn () => [
                    'cve_familia' => $this->articulo->familia->cve_familia,
                    'clave' => $this->articulo->familia->clave,
                    'nombre' => $this->articulo->familia->nombre,
                    'tipo_bien' => $this->when($this->articulo->familia->relationLoaded('tipoBien'), fn () => [
                        'cve_tipo' => $this->articulo->familia->tipoBien->cve_tipo,
                        'clave' => $this->articulo->familia->tipoBien->clave,
                        'nombre' => $this->articulo->familia->tipoBien->nombre,
                    ]),
                ]),
            ]),
            'modelo' => $this->whenLoaded('modelo', fn () => $this->modelo ? [
                'cve_modelo' => $this->modelo->cve_modelo,
                'nombre_modelo' => $this->modelo->nombre_modelo,
                'marca' => $this->when($this->modelo->relationLoaded('marca') && $this->modelo->marca, fn () => $this->modelo->marca->nombre_marca),
            ] : null),
            'aula' => $this->whenLoaded('aula', fn () => $this->aula ? [
                'cve_aula' => $this->aula->cve_aula,
                'nombre' => $this->aula->nombre,
            ] : null),
            'encargado' => $this->whenLoaded('encargado', fn () => $this->encargado ? [
                'cve_persona' => $this->encargado->cve_persona,
                'nombre' => trim($this->encargado->nombre.' '.($this->encargado->apellido_paterno ?? '')),
            ] : null),
        ];
    }
}
