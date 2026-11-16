<?php

namespace App\Http\Requests;

use App\Services\BienesService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditoriaDetalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'encontrado' => ['sometimes', 'boolean'],
            'estado_encontrado' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', BienesService::ESTADOS_FISICOS)],
            'observaciones_evidencia' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado_encontrado.in' => 'Estado físico inválido. Permitidos: '.implode(', ', BienesService::ESTADOS_FISICOS),
        ];
    }
}
