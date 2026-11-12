<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioSiia extends Model
{
    protected $table = 'folio_siia';

    protected $primaryKey = 'cve_folio';

    public $timestamps = false;

    protected $fillable = [
        'clave_siia',
        'cve_persona',
        'fecha',
        'hora',
        'estado',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }
}
