<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraMovimiento extends Model
{
    protected $table = 'bitacora_movimientos';

    protected $primaryKey = 'cve_movimiento';

    public $timestamps = false;

    protected $fillable = [
        'cve_bien',
        'cve_motivo',
        'cve_persona_accion',
        'fecha_movimiento',
        'observaciones',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'cve_bien', 'cve_bien');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoMovimiento::class, 'cve_motivo', 'cve_motivo');
    }

    public function personaAccion(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona_accion', 'cve_persona');
    }
}
