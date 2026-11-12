<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Articulo extends Model
{
    protected $table = 'articulos';

    protected $primaryKey = 'cve_articulo';

    public $timestamps = false;

    protected $fillable = [
        'clave',
        'nombre',
        'cve_familia',
    ];

    public function familia(): BelongsTo
    {
        return $this->belongsTo(FamiliaArticulo::class, 'cve_familia', 'cve_familia');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'cve_articulo', 'cve_articulo');
    }
}
