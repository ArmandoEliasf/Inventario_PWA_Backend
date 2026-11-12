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
| GET | `/api/bienes?per_page=20&page=1` | Profesor | Listado paginado. Admin: todos; profesor: solo suyos; alumno: 403 |
| GET | `/api/bienes/{cve_bien}` | Profesor | Detalle + `qr_url` para pintar el QR |
| POST | `/api/bienes` | Profesor | Crear bien (QR y código de barras automáticos) |
| PUT/PATCH | `/api/bienes/{cve_bien}` | Profesor (propio) | Editar bien |
| DELETE | `/api/bienes/{cve_bien}` | Profesor (propio) | Enviar a papelera |

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

Errores posibles:
| Código | Situación |
|---|---|
| 401 | Sin token / token inválido o revocado |
| 403 | Alumno consultando; profesor operando un bien ajeno |
| 404 | Bien inexistente |
| 422 | Validación fallida; identificador duplicado; bien ya en papelera; bien prestado |
