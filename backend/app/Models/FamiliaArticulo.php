<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamiliaArticulo extends Model
{
    protected $table = 'familias_articulos';

    protected $primaryKey = 'cve_familia';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
        'cve_tipo',
    ];

    public function tipoBien(): BelongsTo
    {
        return $this->belongsTo(TipoBien::class, 'cve_tipo', 'cve_tipo');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class, 'cve_familia', 'cve_familia');
    }
}
