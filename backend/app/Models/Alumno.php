<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alumno extends Model
{
    protected $table = 'alumnos';

    protected $primaryKey = 'cve_alumno';

    public $timestamps = false;

    protected $fillable = [
        'cve_persona',
        'matricula',
        'fecha_inscrito',
        'inscrito',
        'activo',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }
}
