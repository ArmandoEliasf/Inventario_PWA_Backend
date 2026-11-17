# 11 — Referencia API

Todos los endpoints. Base: `http://localhost:8000/api`. Formato JSON.
Envelope: `{"data": ...}` en lecturas; `{"message", "data"}` en
mutaciones. Paginación estilo Laravel (`data`, `links`, `meta`).

## Autenticación

`Authorization: Bearer <token Sanctum>` (obtenido en `POST /api/login`,
expira en 480 min). CORS configurado para el origen de la PWA (ver
[12-cors-y-pwa.md](12-cors-y-pwa.md)).

## Códigos de respuesta estándar

| Código | Cuándo |
|---|---|
| 200 | Lectura/acción correcta |
| 201 | Creación (bienes, préstamos, auditorías, detalles) |
| 401 | Sin token / token expirado |
| 403 | Rol insuficiente o bien/recurso ajeno |
| 404 | Recurso inexistente |
| 422 | Validación (payload, estado del recurso, duplicados) |
| 429 | Rate limit del login (10/min) |
| 500 | Error no controlado |

## Auth

| Método | Ruta | Auth | Body | Respuesta |
|---|---|---|---|---|
| POST | `/login` | — (throttle 10/min) | `{usuario, contrasena}` | `{token, usuario:{cve_usuario, nombre, rol}}` |
| POST | `/logout` | token | — | `{message}` |
| GET | `/me` | token | — | `{usuario:{...,rol,activo}, persona:{cve_persona, nombre, matricula?}}` |

Errores: 422 credenciales inválidas / usuario inactivo.

## Aulas

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/aulas?cve_edificio=` | token | Pestañas de bienes por aula con `total_bienes` (profesor: sus aulas; admin: todas) |
| GET | `/aulas/edificio/{edificio}` | token | Aulas `{cve_aula, nombre}` de un edificio (catálogo) |

## Bienes

| Método | Ruta | Body/Query | Errores |
|---|---|---|---|
| GET | `/bienes` | `?vista=general\|papelera&cve_aula=&cve_edificio=&per_page=1..100` | 403 estudiante; 422 query inválida |
| GET | `/bienes/{bien}` | — | 404 / 403 ajeno |
| POST | `/bienes` | ver §Validación Bienes | 422 identificador duplicado, 201 con `data` + `qr_url` |
| PUT | `/bienes/{bien}` | igual que POST (sometimes) | 422 / 403 / 404 |
| DELETE | `/bienes/{bien}` | — | 422 prestado/ya en papelera |
| PUT | `/bienes/{bien}/restaurar` | — | 422 si no está en papelera |
| DELETE | `/bienes/{bien}/purgar` | — | 422 si activo o prestado |
| GET | `/bienes/por-codigo/{codigo}` | código libre (URL QR, barras, identificador) | 404 desconocido, 403 ajeno |
| POST | `/bienes/{bien}/foto` | multipart `foto` (jpg/jpeg/png/webp ≤ 5 MB) | 422 formato/peso, 403 |
| GET | `/bienes/{bien}/foto` | — | stream de la imagen; 404 sin foto |

Detalle del recurso `data` (BienResource) y del identificador/código de
barras: [04-modulo-bienes.md](04-modulo-bienes.md).

## Préstamos

| Método | Ruta | Body | Acción / Estado |
|---|---|---|---|
| GET | `/prestamos` | `?vista=solicitados\|recibidos\|general&per_page=` | historial paginado |
| GET | `/prestamos/{prestamo}` | — | detalle (403 ajeno) |
| POST | `/prestamos` | `{cve_bien, fecha_devolucion_pactada?, observaciones?}` | solicita → `pendiente` (admin: `aprobado`) |
| POST | `/prestamos/{prestamo}/aceptar` | — | `pendiente → aprobado` (encargado/admin) |
| POST | `/prestamos/{prestamo}/devolver` | — | `aprobado → por_devolver` (solicitante; admin → `devuelto`) |
| POST | `/prestamos/{prestamo}/confirmar-devolucion` | — | `por_devolver → devuelto` (encargado/admin) |

Errores típicos: 422 bien prestado/en papelera/propio/con préstamo
activo o fecha pasada; 403 acciones de terceros.

## Auditorías

| Método | Ruta | Body |
|---|---|---|
| GET | `/auditorias?per_page=` | — (resumen con `total_detalles`) |
| POST | `/auditorias` | `{observaciones_generales?}` (≤1000) |
| GET | `/auditorias/{auditoria}` | — (con `detalles[]` y `coincide_estado`) |
| POST | `/auditorias/{auditoria}/detalles` | `{cve_bien, encontrado?, estado_encontrado?, observaciones_evidencia?}` |
| PUT | `/auditorias/{auditoria}/detalles/{detalle}` | mismos campos, sometimes+nullable |
| DELETE | `/auditorias/{auditoria}/detalles/{detalle}` | — |

Errores: 403 estudiante/ajeno, 422 bien duplicado o estado inválido.

## Reportes

Datos crudos para formatos del front (contrato con ejemplos:
`../docs/frontend-reportes.md`).

| Método | Ruta | Query |
|---|---|---|
| GET | `/reportes/resguardo-individual/{persona}` | `cve_aula` |
| GET | `/reportes/encabezado-reporte/{persona}` | — |
| GET | `/reportes/encabezado-direccion/{persona}` | — |
| GET | `/reportes/movimiento-interno/{prestamo}` | — |
| GET | `/reportes/bajas` | `cve_adscripcion` (solo admin) |
| GET | `/reportes/mantenimiento` | `estado_fisico` (default deteriorado), `cve_adscripcion` |
| GET | `/reportes/trazabilidad/{bien}` | — |
| GET | `/reportes/valor-por-aula` | `cve_adscripcion` |
| GET | `/reportes/prestamos-no-devueltos` | `cve_adscripcion` |
| GET | `/reportes/conteo-estado-fisico` | `cve_adscripcion` |
| GET | `/reportes/conteo-estado-prestamo` | `cve_adscripcion` |

Acceso: Estudiante 403; profesor solo lo suyo; por persona: dueño o admin.

## Información (catálogos para formularios)

| Método | Ruta | Query |
|---|---|---|
| GET | `/informacion/aulas` | — (filtra por adscripción del actor) |
| GET | `/informacion/personal/carrera/{carrera}` | — |
| GET | `/informacion/profesores` | — |
| GET | `/informacion/bienes` | `cve_aula` (profesor: los suyos; estudiante 403) |
| GET | `/informacion/bienes/disponibles` | `cve_aula` (abierto a todos) |
| GET | `/informacion/tipos-bien` | — |
| GET | `/informacion/familias/tipo/{tipo}` | — |
| GET | `/informacion/articulos/familia/{familia}` | — |
| GET | `/informacion/edificios` | — |
| GET | `/informacion/tipos-aula` | — |
| GET | `/informacion/marcas` | — |
| GET | `/informacion/modelos` | — |
| GET | `/informacion/bien/qr/{bien}` | — (`codigo_qr` + `qr_url`) |
| GET | `/informacion/valor-bienes-cargo/{aula}` | — (agrupado + totales) |
| GET | `/informacion/valor-bienes-cargo-detalle/{aula}` | — |
| GET | `/informacion/bienes-por-aula/{aula}` | — |

## Health

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/up` | Health check de Laravel (sin auth) |

## Ejemplos

```http
POST /api/login
{"usuario": "admin", "contrasena": "password"}

POST /api/bienes
Authorization: Bearer 1|xxxx
Content-Type: multipart/form-data
nombre=Proyector Epson&cve_articulo=21&identificador=0000021
&costo_unitario=12500.50&foto=<archivo>

GET /api/bienes/por-codigo/EQ-01-0021-0000021
→ {"data": {...BienResource...}, "qr_url": "http://localhost:5173/bienes/12"}
```
