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

## Resolución del actor (TEMPORAL)

Mientras no exista autenticación, `BienController::actor()` toma el header:

```
X-Usuario: <cve_usuario>
```

- Sin header o usuario inexistente/inactivo → **401**.
- El service verifica el rol leyendo la relación `usuario.role.nombre`.
- **TODO**: al integrar Sanctum (o el mecanismo que se decida), reemplazar por
  `$request->user()` y proteger las rutas con middleware. El header actual es
  solo para poder probar con Bruno/Postman durante el desarrollo.

## Futuras extensiones previstas

- Login de la PWA (alumnos solo para préstamos propios, profesores para
  inventario suyo, admin para todo).
- `Director`: reportes generales (tabla `auditorias`, bitácora).
