# 04 — Módulo Bienes

El corazón del inventario: registro de bienes con identificador de 7
dígitos, código de barras autogenerado, QR, papelera, purga y resolución
de códigos escaneados.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/BienesService.php` | Reglas de negocio (438 líneas) |
| `app/Services/CodigoBarrasService.php` | Formato del código de barras |
| `app/Http/Controllers/BienController.php` | HTTP + validación de query |
| `app/Http/Requests/StoreBienRequest.php` | Validación de alta |
| `app/Http/Requests/UpdateBienRequest.php` | Validación de edición |
| `app/Http/Resources/BienResource.php` | Detalle completo |
| `app/Http/Resources/BienResumenResource.php` | Listado (resumen) |
| `app/Models/Bien.php` | Eloquent + `#[ObservedBy(BienObserver)]` |
| `tests/Feature/BienesCrudTest.php` | 17 tests |
| `tests/Feature/BienesVistasTest.php` | 20 tests |
| `tests/Feature/BienesPorCodigoTest.php` | 8 tests |

## 2. Constantes de negocio (BienesService)

```php
ESTADOS_FISICOS = ['bueno', 'regular', 'deteriorado']  // no existe 'reparacion'
VISTAS          = ['general', 'papelera']
ROL_ADMIN       = 'Administrador'
ROL_PROFESOR    = 'Profesor'
ROL_ALUMNO      = 'Estudiante'
```

## 3. Reglas de negocio

1. **Identificador**: 7 dígitos exactos (`/^[0-9]{7}$/`), único GLOBAL.
   Se guarda en `bienes.no_serie` (columna UNIQUE). Puede iniciar con
   ceros (`0000001`) — se guarda como string.
2. **Código de barras** (autogenerado en crear/editar):
   `{TIPO}-{FAMILIA}-{ARTICULO}-{identificador}`, p. ej.
   `EQ-01-0021-0000007`, a partir de la cadena
   `articulo.familia.tipoBien`.
3. **QR**: tras el insert, `codigo_qr = 'bienes/{cve_bien}'`. La URL
   impresa en el QR es `{FRONTEND_URL}/bienes/{id}` (`urlQr()`).
4. **Propiedad**: el profesor solo ve/edita/envía a papelera bienes
   donde es `cve_encargado`. Quien crea un bien queda como encargado
   (salvo que el admin asigne otro `cve_encargado`).
5. **Papelera reversible** (`activo = false`) + **purga** (DELETE físico
   con dependencias).

## 4. Funciones de BienesService, una por una

### `rolDe(Usuario): ?string`
Nombre del rol del actor desde la relación `role`. Base de todo el
control de acceso del sistema.

### `esAdmin(Usuario): bool` / `esProfesor(Usuario): bool`
Comparan el rol contra las constantes.

### `puedeConsultar(Usuario): bool`
`esAdmin || esProfesor`. Es el gate usado por todos los módulos para
bloquear al Estudiante (403 "El alumno no puede consultar el inventario.").

### `listar(Usuario $actor, int $perPage, array $filtros): LengthAwarePaginator`
Listado paginado de bienes.
- `filtros['vista']`: `'general'` (activos) o `'papelera'` (inactivos).
- `filtros['cve_aula']`: bienes de un aula concreta (pestaña por aula).
- `filtros['cve_edificio']`: solo admin — bienes de todas las aulas de
  un edificio (vía `whereHas('aula')`).
- No admin: `where('cve_encargado', $actor->cve_persona)`.
- Eager load `encargado`; orden `cve_bien DESC`.

### `aulasDe(Usuario, ?int $cveEdificio): Collection`
Aulas para las pestañas "por aula" con `total_bienes` (COUNT de bienes
activos del actor en cada aula, JOIN con edificio para mostrar el
nombre del edificio). Profesor: solo aulas donde tiene bienes; admin:
todas (con filtro de edificio opcional).

### `obtener(Usuario, int $cveBien): Bien`
Detalle de un bien con eager load de
`articulo.familia.tipoBien`, `modelo.marca`, `aula`, `encargado`.
- 404 si no existe (`findOrFail`).
- 403 si no admin y `cve_encargado !== actor->cve_persona`.
Es la **función de acceso base**: la usan editar, papelera, purga, fotos
y trazabilidad, garantizando el mismo control en todas las rutas.

### `obtenerPorCodigo(Usuario, string $codigo): Bien`
Resolución del escaneo (endpoint del lector de la PWA). Acepta:
1. URL del QR (`.../bienes/12`) → regex `/bienes\/(\d+)$/` → acceso por
   clave directa.
2. Código de barras (`EQ-01-0021-0000007`).
3. Identificador / no_serie (`0000007`).
   (2 y 3 se buscan por `codigo_barras | codigo_qr | no_serie`).
- 404 si ningún bien coincide; 403 si el bien es ajeno (igual que
  `obtener`). El Estudiante queda bloqueado por el 403 de propiedad
  (su cve_persona nunca es el encargado).

### `crear(Usuario, array $datos): Bien`
Alta de un bien.
- Gate: solo admin o profesor (403).
- Genera el código de barras ANTES de guardar (`CodigoBarrasService`).
- 422 si el identificador ya está usado (`identificadorYaUsado`).
- Asigna `estado_prestamo = 'disponible'`, `activo = true`, fecha hoy.
- Encargado: admin puede asignar `cve_encargado`; profesor queda él.
- `DB::transaction`: guarda el bien, luego `codigo_qr =
  'bienes/{id}'` con `saveQuietly()` (evita re-disparar el observer).
- Si viene `foto` (multipart), `FotoService::subir`; si la subida
  falla, se hace rollback con `$bien->delete()`.
- Devuelve el bien recargado con todas las relaciones.

### `editar(Usuario, int $cveBien, array $datos): Bien`
Edición. Reutiliza `obtener` (403 propiedad) y bloquea editar bienes de
la papelera si no eres admin. Si cambió `cve_articulo`, regenera el
prefijo del código de barras manteniendo el identificador. Campos
editables: `nombre`, `descripcion`, `cve_modelo`, `cve_aula`,
`costo_unitario`, `no_factura`, `estado_fisico`, `cve_articulo`; el
admin además puede cambiar `cve_encargado`. 422 si el nuevo
identificador pertenece a otro bien.

### `guardarFoto(Usuario, int $cveBien, UploadedFile): Bien`
`obtener()` + `FotoService::subir()` (ver [05-modulo-fotos.md](05-modulo-fotos.md)).

### `enviarPapelera(Usuario, int $cveBien): Bien`
- Solo bienes `activo = true` (422 si ya está en papelera).
- Solo bienes `estado_prestamo = 'disponible'` (422 si prestado).
- `activo = false` + bitácora con motivo **"Baja Definitiva"**
  (motivo semánticamente imperfecto: la baja es reversible; pendiente
  sugerido un motivo propio).

### `restaurar(Usuario, int $cveBien): Bien`
- Solo bienes en papelera (422 si `activo = true`).
- `activo = true` + bitácora motivo **"Restauración"**.

### `purgar(Usuario, int $cveBien): void`
Borrado definitivo desde la papelera (422 si está activo o prestado).
En una transacción:
1. Bitácora de rastro con `cve_bien = null` (el bien ya no existirá):
   motivo "Baja Definitiva" con descripción del bien (código + serie).
2. `auditoriasDetalle()->delete()`, `prestamos()->delete()`,
   `movimientos()->delete()`.
3. `FotoService::borrarArchivo($foto_drive_id)` — elimina el archivo.
4. `$bien->delete()`.

### `identificadorYaUsado(string, ?int $excluir): bool` (privado)
Existencia en `no_serie`; al editar excluye el propio bien.

### `urlQr(Bien): string`
`rtrim(FRONTEND_URL) . '/' . codigo_qr` → la URL que se imprime en el
código QR y que el escáner devuelve (y `obtenerPorCodigo` resuelve).

## 5. CodigoBarrasService

Única función pública:

```php
construir(int $cveArticulo, string $identificador): string
```
Busca el artículo con `familia.tipoBien` y compone
`{tipoBien.clave}-{familia.clave}-{articulo.clave}-{identificador}`.
404 (`findOrFail`) si el artículo no existe.

## 6. Validación

### StoreBienRequest (alta)
| Campo | Reglas |
|---|---|
| `nombre` | required, string, max:100 |
| `descripcion` | nullable, string |
| `cve_articulo` | required, integer, exists |
| `identificador` | required, regex 7 dígitos |
| `cve_modelo` / `cve_aula` / `cve_encargado` | nullable, integer, exists |
| `costo_unitario` | nullable, numeric, 0–99999999.99 |
| `no_factura` | nullable, max:50, UNIQUE |
| `estado_fisico` | nullable, in:bueno/regular/deteriorado |
| `foto` | sometimes, image, mimes:jpg,jpeg,png,webp, max:5120 KB |

### UpdateBienRequest (edición)
Igual pero con `sometimes` en los obligatorios y UNIQUE de `no_factura`
excluyendo el propio bien (toma la clave de la ruta).

## 7. Serialización

**BienResumenResource** (listados): `cve_bien`, `identificador`
(= no_serie), `nombre`, `encargado {cve_persona, nombre}`, `estado_fisico`,
`tiene_foto`. Ligero para tablas de la PWA.

**BienResource** (detalle/creación/edición): todo lo anterior + `codigo_barras`,
`codigo_qr`, `no_factura`, `descripcion`, `foto_url` (endpoint proxy
`/api/bienes/{id}/foto` si tiene foto), `foto_drive_id`, `costo_unitario`,
`estado_prestamo`, `activo`, `fecha_registro`, y los bloques anidados
`articulo` (con `familia.tipo_bien`), `modelo` (con `marca`), `aula`,
`encargado`.

## 8. Endpoints (BienController)

| Método | Ruta | Función del service |
|---|---|---|
| GET | `/api/bienes?vista=&cve_aula=&cve_edificio=&per_page=` | `listar` |
| GET | `/api/bienes/{bien}` | `obtener` |
| POST | `/api/bienes` | `crear` |
| PUT | `/api/bienes/{bien}` | `editar` |
| DELETE | `/api/bienes/{bien}` | `enviarPapelera` |
| PUT | `/api/bienes/{bien}/restaurar` | `restaurar` |
| DELETE | `/api/bienes/{bien}/purgar` | `purgar` |
| GET | `/api/bienes/por-codigo/{codigo}` | `obtenerPorCodigo` |
| POST | `/api/bienes/{bien}/foto` | `guardarFoto` |
| GET | `/api/bienes/{bien}/foto` | proxy `FotoService::respuesta` |

Respuestas de mutación: `{"message": "...", "data": BienResource}` con
201 en la creación; la creación y el detalle añaden `qr_url`.

Fotos: ver [05-modulo-fotos.md](05-modulo-fotos.md).
