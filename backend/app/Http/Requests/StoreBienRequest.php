<?php

namespace App\Http\Requests;

use App\Services\BienesService;
use App\Services\FotoService;
use Illuminate\Foundation\Http\FormRequest;

class StoreBienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'cve_articulo' => ['required', 'integer', 'exists:articulos,cve_articulo'],
            'identificador' => ['required', 'string', 'regex:/^[0-9]{7}$/'],
            'cve_modelo' => ['nullable', 'integer', 'exists:modelos,cve_modelo'],
            'cve_aula' => ['nullable', 'integer', 'exists:aula,cve_aula'],
            'cve_encargado' => ['nullable', 'integer', 'exists:persona,cve_persona'],
            'costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'no_factura' => ['nullable', 'string', 'max:50', 'unique:bienes,no_factura'],
            'estado_fisico' => ['nullable', 'string', 'in:'.implode(',', BienesService::ESTADOS_FISICOS)],
            'foto' => ['sometimes', 'image', 'mimes:'.implode(',', FotoService::EXTENSIONES), 'max:'.FotoService::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'identificador.regex' => 'El identificador debe ser exactamente 7 dígitos numéricos (puede iniciar con ceros).',
            'estado_fisico.in' => 'Estado físico inválido. Permitidos: '.implode(', ', BienesService::ESTADOS_FISICOS),
            'foto.max' => 'La foto no debe pesar más de '.(FotoService::MAX_KILOBYTES / 1024).' MB.',
        ];
    }
}
