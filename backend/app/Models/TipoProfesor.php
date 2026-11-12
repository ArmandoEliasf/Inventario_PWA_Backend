<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoProfesor extends Model
{
    protected $table = 'tipo_profesor';

    protected $primaryKey = 'cve_tipo_profesor';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class, 'cve_tipo_profesor', 'cve_tipo_profesor');
    }
}
