# 05 — API

Base URL local: `http://localhost:8000` (`php artisan serve`).

## Autenticación (Sanctum)

**POST /api/login**

```json
{ "usuario": "admin", "contrasena": "password" }
```

Respuesta 200:

```json
{
  "token": "1|xxxxxxxxxxxx",
  "usuario": { "cve_usuario": 1, "nombre": "Administrador del Sistema", "rol": "Administrador" }
}
```

Errores: 422 con `{"errors": {"usuario": ["Credenciales inválidas."]}}` si el
usuario no existe, está inactivo o la contraseña es incorrecta. La ruta tiene
`throttle:10,1` (máx. 10 intentos por minuto).

Todas las demás rutas requieren el header:

```
Authorization: Bearer <token>
```

**GET /api/me** — perfil del usuario autenticado (rol, persona, email).
**POST /api/logout** — revoca el token actual.

## Endpoints

| Método | Ruta | Rol mínimo | Descripción |
|---|---|---|---|
| POST | `/api/login` | — | Login, devuelve token |
| GET | `/api/me` | Cualquier token | Perfil autenticado |
| POST | `/api/logout` | Cualquier token | Cerrar sesión |
| GET | `/api/aulas?cve_edificio=X` | Profesor | Pestañas "por aula" con conteo de bienes activos. Profesor: solo sus aulas; admin: todas (filtro por edificio opcional); alumno: 403 |
| GET | `/api/bienes?vista=general\|papelera&cve_aula=X&cve_edificio=X&per_page=20` | Profesor | Listado **resumido**. Profesor: solo suyos; admin: todos (filtros por aula/edificio); alumno: 403 |
| GET | `/api/bienes/{cve_bien}` | Profesor | **Detalle completo** + `qr_url` para pintar el QR |
| POST | `/api/bienes` | Profesor | Crear bien (QR y código de barras automáticos) |
| PUT/PATCH | `/api/bienes/{cve_bien}` | Profesor (propio) | Editar bien |
| DELETE | `/api/bienes/{cve_bien}` | Profesor (propio) | Enviar a papelera |
| PUT | `/api/bienes/{cve_bien}/restaurar` | Profesor (propio) | Restaurar bien de la papelera (`activo=true` + bitácora "Restauración") |
| DELETE | `/api/bienes/{cve_bien}/purgar` | Profesor (propio) | Eliminar definitivamente (borra el bien y sus dependencias) |

## Listado vs detalle (dos niveles de información)

**Listado resumido** (`GET /api/bienes`, recurso `BienResumenResource`): solo los
campos para la tarjeta de lista — `cve_bien`, `identificador`, `nombre`,
`encargado` y `estado_fisico`. El botón "ver detalles" lleva al endpoint show.

**Detalle completo** (`GET /api/bienes/{id}`, recurso `BienResource`): toda la
información — código de barras, QR, `identificador`, `no_factura`, `descripcion`,
**`foto_url`/`foto_drive_id`**, costo, catálogos (artículo/familia/tipo, modelo,
aula, encargado) y `qr_url` calculada.

## Parámetros del listado (GET /api/bienes)

| Parámetro | Valores | Uso |
|---|---|---|
| `vista` | `general` (default), `papelera` | `general`: bienes activos; `papelera`: `activo=false` |
| `cve_aula` | id de aula | Pestaña "por aula" |
| `cve_edificio` | id de edificio | (admin) todas las aulas de un edificio |
| `per_page` | 1–100 | Paginación (default 20) |

Profesor siempre está limitado a `cve_encargado = su persona` en todas las vistas.

## GET /api/aulas

Pestañas "bienes por aula". Solo devuelve aulas con bienes activos y su conteo.

```json
{
  "data": [
    { "cve_aula": 7, "nombre": "A-101", "cve_edificio": 3, "edificio": "DSM", "total_bienes": 17 },
    { "cve_aula": 8, "nombre": "A-102", "cve_edificio": 3, "edificio": "DSM", "total_bienes": 16 }
  ]
}
```

- Profesor: solo las aulas donde tiene bienes propios.
- Admin: todas las aulas; con `?cve_edificio=X` se limita a un edificio.
- Alumno: 403.

## POST /api/bienes

```json
{
  "nombre": "Proyector Epson X51",
  "cve_articulo": 500,
  "identificador": "0000001",
  "cve_modelo": 3,
  "cve_aula": 14,
  "cve_encargado": 1,
  "costo_unitario": 8999.99,
  "no_factura": "F-2026-001",
  "descripcion": "Equipo nuevo de aula",
  "estado_fisico": "bueno"
}
```

- Requeridos: `nombre`, `cve_articulo`, `identificador`.
- `cve_encargado` solo aplica para Administrador (el profesor queda como encargado).
- `estado_fisico`: `bueno` (default), `regular`, `deteriorado`.

Respuesta 201:

```json
{
  "message": "Bien creado correctamente.",
  "data": {
    "cve_bien": 101,
    "nombre": "Proyector Epson X51",
    "codigo_barras": "EO-E3-0500-0000001",
    "codigo_qr": "bienes/101",
    "identificador": "0000001",
    "estado_fisico": "bueno",
    "estado_prestamo": "disponible",
    "activo": true,
    "articulo": { "clave": "0500", "nombre": "...", "familia": { "clave": "E3", "tipo_bien": { "clave": "EO" } } },
    "modelo": { "nombre_modelo": "LaserJet 400", "marca": "HP" },
    "aula": { "nombre": "CC-1" },
    "encargado": { "nombre": "Administrador Sistema" }
  },
  "qr_url": "http://localhost:5173/bienes/101"
}
```

## PUT /api/bienes/{id}

Mismos campos, todos opcionales. Reglas especiales:
- Si se envía `cve_articulo`, el prefijo del código de barras se re-genera.
- Si se envía `identificador`, `no_serie` se sincroniza y se valida unicidad global.
- `cve_encargado` solo el admin.

## DELETE /api/bienes/{id}

Respuesta 200 con el bien actualizado (`"activo": false`).

## PUT /api/bienes/{id}/restaurar

Reactiva un bien de la papelera. Registra bitácora con motivo **"Restauración"**
(catálogo `motivo_de_movimiento`, agregado por el backend).

Respuesta 200: `{"message": "Bien restaurado desde la papelera.", "data": {...}}`

Errores:
| Código | Situación |
|---|---|
| 403 | Bien ajeno (profesor) |
| 422 | El bien no está en la papelera (`activo=true`) |

## DELETE /api/bienes/{id}/purgar

Eliminación **física definitiva** de un bien de la papelera. Dentro de una
transacción: borra `auditoria_detalle`, `prestamos` y `bitacora_movimientos`
del bien, luego el bien. Deja un **rastro** en bitácora con `cve_bien = null`
y motivo "Baja Definitiva" (observaciones con nombre, código de barras e
identificador del bien eliminado).

Respuesta 200: `{"message": "Bien eliminado definitivamente."}`

Errores:
| Código | Situación |
|---|---|
| 403 | Bien ajeno (profesor) |
| 422 | El bien no está en la papelera, o tiene un préstamo activo |

Errores posibles:
| Código | Situación |
|---|---|
| 401 | Sin token / token inválido o revocado |
| 403 | Alumno consultando; profesor operando un bien ajeno |
| 404 | Bien inexistente |
| 422 | Validación fallida; identificador duplicado; bien ya en papelera; bien prestado |
