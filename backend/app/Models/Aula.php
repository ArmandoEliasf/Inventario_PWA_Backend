<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aula extends Model
{
    protected $table = 'aula';

    protected $primaryKey = 'cve_aula';

    public $timestamps = false;

    protected $fillable = [
        'cve_edificio',
        'cve_tipo_aula',
        'cve_profesor',
        'cve_adscripcion',
        'nombre',
        'codigo_barras',
        'codigo_qr',
        'capacidad',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class, 'cve_edificio', 'cve_edificio');
    }

    public function tipoAula(): BelongsTo
    {
        return $this->belongsTo(TipoAula::class, 'cve_tipo_aula', 'cve_tipo_aula');
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'cve_profesor', 'cve_profesor');
    }

    public function adscripcion(): BelongsTo
    {
        return $this->belongsTo(Adscripcion::class, 'cve_adscripcion', 'cve_adscripcion');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'cve_aula', 'cve_aula');
    }
}
