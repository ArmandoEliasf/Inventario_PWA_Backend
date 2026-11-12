<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $table = 'marcas';

    protected $primaryKey = 'cve_marca';

    public $timestamps = false;

    protected $fillable = [
        'nombre_marca',
    ];

    public function modelos(): HasMany
    {
        return $this->hasMany(Modelo::class, 'cve_marca', 'cve_marca');
    }
}
