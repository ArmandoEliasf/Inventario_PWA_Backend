<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $primaryKey = 'cve_auditoria';

    public $timestamps = false;

    protected $fillable = [
        'fecha_auditoria',
        'cve_auditor',
        'observaciones_generales',
    ];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_auditor', 'cve_persona');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AuditoriaDetalle::class, 'cve_auditoria', 'cve_auditoria');
    }
}
