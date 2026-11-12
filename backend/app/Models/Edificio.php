<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edificio extends Model
{
    protected $table = 'edificio';

    protected $primaryKey = 'cve_edificio';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'abreviatura',
        'activo',
    ];

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'cve_edificio', 'cve_edificio');
    }
}
