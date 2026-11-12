<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $table = 'area';

    protected $primaryKey = 'cve_area';

    public $timestamps = false;

    protected $fillable = [
        'cve_area_padre',
        'abreviatura',
        'nombre',
        'activo',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cve_area_padre', 'cve_area');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'cve_area_padre', 'cve_area');
    }

    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class, 'cve_area', 'cve_area');
    }
}
