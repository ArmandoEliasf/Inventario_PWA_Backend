<?php

namespace App\Http\Requests;

use App\Services\BienesService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuditoriaDetalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cve_bien' => ['required', 'integer', 'exists:bienes,cve_bien'],
            'encontrado' => ['nullable', 'boolean'],
            'estado_encontrado' => ['nullable', 'string', 'in:'.implode(',', BienesService::ESTADOS_FISICOS)],
            'observaciones_evidencia' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado_encontrado.in' => 'Estado físico inválido. Permitidos: '.implode(', ', BienesService::ESTADOS_FISICOS),
        ];
    }
}
