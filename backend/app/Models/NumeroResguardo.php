<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumeroResguardo extends Model
{
    protected $table = 'numero_resguardo';

    protected $primaryKey = 'cve_resguardo_pk';

    public $timestamps = false;

    protected $fillable = [
        'no_resguardo',
        'cve_persona',
        'fecha',
        'hora',
        'estado',
        'fecha_registro',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }
}
