# 04 — Permisos y usuarios

## Roles relevantes

| Rol (tabla `roles`) | Alcance |
|---|---|
| Administrador | Todo el inventario, todos los bienes |
| Profesor | Solo los bienes donde es encargado (`bienes.cve_encargado` = su `persona.cve_persona`) |
| Estudiante (alumno) | Sin acceso al inventario |
| Director | Sin operaciones CRUD definidas hoy |

## Matriz de permisos

| Acción | Alumno | Profesor | Administrador |
|---|:---:|:---:|:---:|
| Consultar inventario (index/show) | ❌ | ✅ solo propios | ✅ todos |
| Crear bienes | ❌ | ✅ | ✅ |
| Editar bienes | ❌ | ✅ solo propios | ✅ todos |
| Enviar a papelera | ❌ | ✅ solo propios | ✅ todos |
| Registrar usuarios | — | — | — |

El **registro de usuarios queda fuera de la API**: el área de programación crea
las cuentas directamente en la base de datos. La app solo consume las cuentas
existentes (login y permisos, cuando exista auth).

## Concepto de "bien propio"

- `Bien` es propio de un profesor si `cve_encargado` apunta a su `cve_persona`.
- Cuando un profesor **crea** un bien, queda automáticamente como encargado
  (no puede asignar otro encargado).
- El administrador sí puede asignar/reasignar `cve_encargado` en creación y edición.
- La verificación ocurre en `BienesService::obtener()`, que es el punto de entrada
  de `editar()` y `enviarPapelera()` (evita divergencias de reglas entre acciones).

## Resolución del actor

Autenticación por **Laravel Sanctum** (token bearer):

1. `POST /api/login` con `{"usuario": "...", "contrasena": "..."}` → devuelve
   `token` (formato `id|texto`), datos del usuario y su rol.
2. La PWA guarda el token y envía en cada petición:
   `Authorization: Bearer <token>`.
3. `BienController::actor()` = `$request->user()` (resuelto por el middleware
   `auth:sanctum`). Rutas protegidas en `routes/api.php`.
4. `POST /api/logout` revoca el token actual; `GET /api/me` devuelve el perfil.
5. Si el token es inválido/expirado → 401; usuario con `activo=false` no puede
   hacer login.

El login valida contra `usuarios.usuario` y `usuarios.contrasena_hash`
(`Hash::check`, bcrypt) vía `Usuario::getAuthPassword()`.

## Futuras extensiones previstas

- Expiración de tokens (`SANCTUM_...` / abilities).
- `Director`: reportes generales (tabla `auditorias`, bitácora).
