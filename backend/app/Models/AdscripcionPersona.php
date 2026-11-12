<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdscripcionPersona extends Model
{
    protected $table = 'adscripcion_persona';

    protected $primaryKey = 'cve_adscripcion_persona';

    public $timestamps = false;

    protected $fillable = [
        'cve_adscripcion',
        'cve_persona',
        'activo',
        'fecha_registro',
    ];

    public function adscripcion(): BelongsTo
    {
        return $this->belongsTo(Adscripcion::class, 'cve_adscripcion', 'cve_adscripcion');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }
}
