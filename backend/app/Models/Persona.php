<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
    protected $table = 'persona';

    protected $primaryKey = 'cve_persona';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'cve_carrera',
        'cve_nivel_estudio',
        'fecha_alta',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'cve_persona', 'cve_persona');
    }

    public function profesor(): HasOne
    {
        return $this->hasOne(Profesor::class, 'cve_persona', 'cve_persona');
    }

    public function alumno(): HasOne
    {
        return $this->hasOne(Alumno::class, 'cve_persona', 'cve_persona');
    }

    public function adscripciones(): BelongsToMany
    {
        return $this->belongsToMany(Adscripcion::class, 'adscripcion_persona', 'cve_persona', 'cve_adscripcion', 'cve_persona', 'cve_adscripcion')
            ->withPivot('activo', 'fecha_registro');
    }

    public function bienesResguardados(): HasMany
    {
        return $this->hasMany(Bien::class, 'cve_encargado', 'cve_persona');
    }
}
