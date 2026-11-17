# 03 — Autenticación y roles

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Http/Controllers/AuthController.php` | login / logout / me |
| `app/Models/Usuario.php` | usuario autenticable (`HasApiTokens`), tabla `usuarios` |
| `config/sanctum.php` | expiración `SANCTUM_TOKEN_EXPIRATION` (480 min) |
| `routes/api.php` | `POST /api/login` con `throttle:10,1`; el resto dentro de `auth:sanctum` |
| `tests/Feature/AuthTest.php` | 7 tests del flujo completo |

## 2. Flujo de autenticación

```
1. POST /api/login {usuario, contrasena}        (throttle 10/min)
2. AuthController busca el usuario por 'usuario'
   - falla si no existe, si !activo o si Hash::check falla → 422
     {"message": "...", "errors": {"usuario": ["Credenciales inválidas."]}}
3. createToken('pwa') → token Sanctum guardado en personal_access_tokens
4. Respuesta 200:
   {"token": "1|abc...",
    "usuario": {"cve_usuario", "nombre", "rol"}}
5. La PWA envía el token en cada petición:  Authorization: Bearer <token>
   El guard 'sanctum' resuelve el Usuario (Eloquent provider configurado
   en config/auth.php → App\Models\Usuario).
6. Expiración: si created_at del token tiene más de 480 min → 401.
7. POST /api/logout revoca currentAccessToken().
```

Características relevantes para la PWA:

- **Stateless** (token, no cookies): funciona offline-first con
  almacenamiento local y con CORS sin credenciales.
- **El rol llega en el login** (`rol`) para que la PWA ajuste la interfaz
  de inmediato, y `GET /api/me` lo confirma con detalle.

## 3. GET /api/me

Devuelve el usuario autenticado + bloque `persona`:

```json
{
  "usuario": {"cve_usuario": 3, "usuario": "profesor", "nombre": "...",
               "email": "...", "rol": "Profesor", "activo": true},
  "persona": {"cve_persona": 3, "nombre": "Carlos Sánchez Rodríguez",
               "matricula": null}
}
```

- `persona.nombre` es nombre + apellidos compuestos en PHP.
- `matricula` solo viene para alumnos (relación `persona.alumno`), útil
  para pantallas de préstamos del Estudiante.

## 4. Roles y modelo de autorización

Roles del catálogo que el backend distingue (constantes en
`BienesService`):

| Constante | Rol | Uso |
|---|---|---|
| `ROL_ADMIN` | Administrador | Acceso total; asigna encargados; ve todo |
| `ROL_PROFESOR` | Profesor | Solo bienes donde es `cve_encargado` |
| `ROL_ALUMNO` | Estudiante | Bloqueado del inventario (403); solicita préstamos y consulta lo suyo |

Otros roles (Director, etc.) reciben el trato de "profesor" en las
consultas con filtro por propiedad, y de "no admin" en todo lo demás.

### Cómo se resuelve el rol

```php
BienesService::rolDe(Usuario $u)      → $u->role->nombre ?? null
BienesService::esAdmin(Usuario $u)    → rol === 'Administrador'
BienesService::esProfesor(Usuario $u) → rol === 'Profesor'
BienesService::puedeConsultar(Usuario $u) → esAdmin || esProfesor
```

Los services instancian `BienesService` para reutilizar estos helpers
(`ReportesService`, `AuditoriasService`, `InformacionService` y
`PrestamosService` definen su propio `esAdmin` delegando en
`BienesService::ROL_ADMIN`).

### Matriz de permisos global

| Acción | Administrador | Profesor | Estudiante |
|---|---|---|---|
| Consultar bienes/aulas/papeles | ✔ todo | ✔ solo lo suyo | ✖ 403 |
| Crear/editar bienes | ✔ | ✔ (los suyos) | ✖ 403 |
| Papelera / restaurar / purgar | ✔ | ✔ (los suyos) | ✖ 403 |
| Escanear código → detalle | ✔ | ✔ (los suyos) | ✖ 403 |
| Crear auditoría / registrar escaneos | ✔ | ✔ (bienes suyos) | ✖ 403 |
| Solicitar préstamo | ✔ (auto-aprobado) | ✔ | ✔ |
| Aceptar / confirmar préstamo | ✔ | ✔ (encargado del bien) | ✖ 403 |
| Reportes | ✔ todo (+ `?cve_adscripcion=`) | ✔ solo lo suyo | ✖ 403 |
| Catálogos de información | ✔ | ✔ | ✔ |
| Listas de bienes de información | ✔ todo | ✔ solo lo suyo | ✖ 403 (excepto `bienes/disponibles`) |
| Foto del bien (ver/subir) | ✔ | ✔ (los suyos) | ✖ 403 |

Detalle por módulo en los documentos 04–09.

## 5. Seguridad

- Contraseñas: `Hash::check` contra `contrasena_hash` (bcrypt 12).
- `usuarios.activo = false` impide el login aunque las credenciales sean
  correctas.
- El token se revoca en logout y expira a los 480 min; el front debe
  capturar el 401 y volver al login.
- Login con rate limiting (`throttle:10,1`) contra fuerza bruta.
- La reglas de propiedad se aplican SIEMPRE en los services, no en los
  controllers, de forma uniforme.
- `.env` no está en git; contiene credenciales reales de Supabase.
