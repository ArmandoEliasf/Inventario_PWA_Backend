<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends User
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $primaryKey = 'cve_usuario';

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'usuario',
        'contrasena_hash',
        'cve_rol',
        'nombre',
        'email',
        'activo',
        'cve_persona',
    ];

    protected $hidden = [
        'contrasena_hash',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena_hash;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'cve_rol', 'cve_rol');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'cve_persona', 'cve_persona');
    }
}
