<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoBien extends Model
{
    protected $table = 'tipos_bien';

    protected $primaryKey = 'cve_tipo';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
    ];

    public function familias(): HasMany
    {
        return $this->hasMany(FamiliaArticulo::class, 'cve_tipo', 'cve_tipo');
    }
}
