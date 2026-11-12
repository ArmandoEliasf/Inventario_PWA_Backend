<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Bien;

class CodigoBarrasService
{
    /**
     * Construye el código de barras con el formato
     * {TIPO}-{FAMILIA}-{ARTICULO}-{IDENTIFICADOR}.
     *
     * @param  int  $cveArticulo  Clave del artículo
     * @param  string  $identificador  7 dígitos numéricos (como string)
     */
    public function construir(int $cveArticulo, string $identificador): string
    {
        $articulo = Articulo::query()
            ->with('familia.tipoBien')
            ->findOrFail($cveArticulo);

        $tipo = $articulo->familia->tipoBien->clave ?? '';
        $familia = $articulo->familia->clave ?? '';
        $claveArticulo = $articulo->clave;

        return "{$tipo}-{$familia}-{$claveArticulo}-{$identificador}";
    }

    public function existe(string $codigoBarras, ?int $excluirBien = null): bool
    {
        $query = Bien::query()->where('codigo_barras', $codigoBarras);

        if ($excluirBien !== null) {
            $query->where('cve_bien', '!=', $excluirBien);
        }

        return $query->exists();
    }
}
