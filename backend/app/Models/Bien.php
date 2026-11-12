<?php

namespace App\Models;

use App\Observers\BienObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([BienObserver::class])]
class Bien extends Model
{
    protected $table = 'bienes';

    protected $primaryKey = 'cve_bien';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo_barras',
        'nfc',
        'codigo_qr',
        'no_serie',
        'no_factura',
        'descripcion',
        'foto_url',
        'foto_drive_id',
        'cve_modelo',
        'cve_articulo',
        'costo_unitario',
        'cve_aula',
        'cve_encargado',
        'estado_fisico',
        'estado_prestamo',
        'activo',
        'fecha_registro',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'costo_unitario' => 'decimal:2',
            'fecha_registro' => 'date',
        ];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class, 'cve_modelo', 'cve_modelo');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'cve_articulo', 'cve_articulo');
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'cve_aula', 'cve_aula');
    }

    public function encargado(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_encargado', 'cve_persona');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class, 'cve_bien', 'cve_bien');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BitacoraMovimiento::class, 'cve_bien', 'cve_bien');
    }

    public function auditoriasDetalle(): HasMany
    {
        return $this->hasMany(AuditoriaDetalle::class, 'cve_bien', 'cve_bien');
    }
}
