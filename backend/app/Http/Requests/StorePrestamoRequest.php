<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cve_bien' => ['required', 'integer', 'exists:bienes,cve_bien'],
            'fecha_devolucion_pactada' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cve_bien.exists' => 'El bien indicado no existe.',
        ];
    }
}
