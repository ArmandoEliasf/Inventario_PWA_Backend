<?php

namespace App\Models;

use App\Observers\PrestamoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([PrestamoObserver::class])]
class Prestamo extends Model
{
    protected $table = 'prestamos';

    protected $primaryKey = 'cve_prestamo';

    public $timestamps = false;

    protected $fillable = [
        'cve_bien',
        'cve_persona_solicita',
        'fecha_solicitud',
        'fecha_devolucion_pactada',
        'fecha_devolucion_real',
        'estado_prestamo',
        'observaciones',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(Bien::class, 'cve_bien', 'cve_bien');
    }

    public function personaSolicita(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona_solicita', 'cve_persona');
    }
}
