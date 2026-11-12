<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAula extends Model
{
    protected $table = 'tipo_aula';

    protected $primaryKey = 'cve_tipo_aula';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'cve_tipo_aula', 'cve_tipo_aula');
    }
}
