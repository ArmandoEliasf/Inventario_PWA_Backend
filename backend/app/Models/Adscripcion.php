<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adscripcion extends Model
{
    protected $table = 'adscripcion';

    protected $primaryKey = 'cve_adscripcion';

    public $timestamps = false;

    protected $fillable = [
        'clave_adscripcion',
        'nombre_adscripcion',
        'estado',
        'fecha_registro',
    ];

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'cve_adscripcion', 'cve_adscripcion');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(AdscripcionPersona::class, 'cve_adscripcion', 'cve_adscripcion');
    }
}
