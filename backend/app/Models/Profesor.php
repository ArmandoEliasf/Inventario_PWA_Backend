<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesor extends Model
{
    protected $table = 'profesor';

    protected $primaryKey = 'cve_profesor';

    public $timestamps = false;

    protected $fillable = [
        'cve_persona',
        'cve_tipo_profesor',
        'cve_area',
        'activo',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }

    public function tipoProfesor(): BelongsTo
    {
        return $this->belongsTo(TipoProfesor::class, 'cve_tipo_profesor', 'cve_tipo_profesor');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'cve_area', 'cve_area');
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class, 'cve_profesor', 'cve_profesor');
    }
}
