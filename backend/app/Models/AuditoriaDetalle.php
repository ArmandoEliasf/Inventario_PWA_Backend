<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaDetalle extends Model
{
    protected $table = 'auditoria_detalle';

    protected $primaryKey = 'cve_auditoria_det';

    public $timestamps = false;

    protected $fillable = [
        'cve_auditoria',
        'cve_bien',
        'encontrado',
        'estado_encontrado',
        'observaciones_evidencia',
    ];

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class, 'cve_auditoria', 'cve_auditoria');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'cve_bien', 'cve_bien');
    }
}
