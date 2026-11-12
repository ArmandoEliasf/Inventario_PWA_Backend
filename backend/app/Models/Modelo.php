<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modelo extends Model
{
    protected $table = 'modelos';

    protected $primaryKey = 'cve_modelo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_modelo',
        'cve_marca',
        'descripcion',
    ];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'cve_marca', 'cve_marca');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(Bien::class, 'cve_modelo', 'cve_modelo');
    }
}
