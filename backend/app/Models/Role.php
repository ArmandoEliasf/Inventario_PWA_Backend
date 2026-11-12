<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'cve_rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_alta',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'cve_rol', 'cve_rol');
    }
}
