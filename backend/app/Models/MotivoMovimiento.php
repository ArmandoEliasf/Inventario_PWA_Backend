<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotivoMovimiento extends Model
{
    protected $table = 'motivo_de_movimiento';

    protected $primaryKey = 'cve_motivo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_motivo',
        'requiere_aprobacion',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(BitacoraMovimiento::class, 'cve_motivo', 'cve_motivo');
    }
}
