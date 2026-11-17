# 06 — Módulo Préstamos

Préstamo de bienes entre personas. El solicitante pide un bien ajeno;
el encargado del bien (o un admin) aprueba; al devolver participan ambos
extremos. El estado del bien se sincroniza automáticamente.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/PrestamosService.php` | Reglas de negocio |
| `app/Http/Controllers/PrestamoController.php` | HTTP + validación de query |
| `app/Http/Requests/StorePrestamoRequest.php` | Validación de la solicitud |
| `app/Http/Resources/PrestamoResource.php` | Detalle |
| `app/Http/Resources/PrestamoResumenResource.php` | Listado/historial |
| `app/Models/Prestamo.php` | Eloquent + `#[ObservedBy(PrestamoObserver)]` |
| `tests/Feature/PrestamosTest.php` | 21 tests |
| `tests/Feature/PrestamosHistorialTest.php` | 8 tests |

## 2. Flujo de estados

```
              solicitar (cualquiera)                aceptar (encargado/admin)
   [no existe] ────────────▶ pendiente ──────────────────────▶ aprobado
                                  │                              │
                                  │                devolver (solo solicitante)
                                  │                admin: directo a devuelto
                                  ▼                              ▼
                              (rechazado              por_devolver ───▶ devuelto
                               implícito:                  │  confirmar
                               nadie acepta)               ▼ (encargado/admin)
                                                                     devuelto
```

Sincronización del bien (`PrestamoObserver`, ver
[10-observadores-y-bitacora.md](10-observadores-y-bitacora.md)):

| Transición del préstamo | El bien queda |
|---|---|
| → `aprobado` | `estado_prestamo = 'prestado'` |
| → `devuelto` | `estado_prestamo = 'disponible'` |
| → `pendiente`, `por_devolver` | sin cambio (sigue 'disponible'/'prestado') |

`ESTADOS_ACTIVOS = ['pendiente', 'aprobado', 'por_devolver']` — estados
en los que el bien está comprometido y no puede tener otra solicitud.

## 3. Vistas del listado (constantes)

| Vista | Qué muestra | Quién |
|---|---|---|
| `solicitados` (default) | Préstamos solicitados por el actor (historial propio) | cualquiera |
| `recibidos` | Préstamos de bienes donde el actor es encargado — el "buzón" para aceptar/confirmar | profesor: los suyos; admin: todos |
| `general` | Todo el historial | solo admin (403 si no) |

## 4. Funciones, una por una

### `listar(Usuario, string $vista, int $perPage): LengthAwarePaginator`
Paginado con eager load `bien.encargado` y `personaSolicita`, orden
`cve_prestamo DESC`. Filtro según vista (tabla anterior). 403 si un no
admin pide `general`.

### `obtener(Usuario, int $cvePrestamo): Prestamo`
Detalle con relaciones. Acceso: solicitante, encargado del bien o
admin (403 en otro caso), 404 si no existe.

### `solicitar(Usuario, array $datos): Prestamo`
Validaciones en orden (todas 422 salvo la primera, 404):
1. El bien existe.
2. `activo = true` (no está en papelera).
3. `estado_prestamo = 'disponible'`.
4. Tiene encargado asignado.
5. El encargado no es el propio solicitante ("No puedes solicitar un
   préstamo de tu propio bien.").
6. No tiene préstamo activo (`tienePrestamoActivo`).
7. `fecha_devolucion_pactada` debe ser futura (`after:now`, en el Request).

Creación en transacción:
- Estado inicial: `'aprobado'` si el actor es admin (auto-aprobación),
  `'pendiente'` para los demás.
- **Marca del bien**: un préstamo creado ya aprobado no pasa por
  `updated()` (el observer solo reacciona a cambios), por eso se marca
  el bien a `prestado` con un `update()` directo.
- Bitácora motivo **"Préstamo"** ("Solicitud de préstamo: ..." /
  "Préstamo auto-aprobado (solicitante administrador): ...").

### `aceptar(Usuario, int $cvePrestamo): Prestamo`
- Solo préstamos `pendiente` (422).
- Solo el encargado del bien o admin (403).
- `pendiente → aprobado` en transacción + bitácora "Préstamo aprobado".
- El bien pasa a `prestado` vía `PrestamoObserver::updated`.

### `devolver(Usuario, int $cvePrestamo): Prestamo`
- Solo préstamos `aprobado` (422).
- Solo el **solicitante** (403) — el admin sí puede devolver el suyo.
- Si el actor es admin: `aprobado → devuelto` directo (el bien vuelve a
  `disponible` por el observer). Si no: `aprobado → por_devolver`.
- Guarda `fecha_devolucion_real = now()`.
- Bitácora motivo **"Devolución"** ("Devolución registrada, pendiente de
  confirmación..." / "Devolución directa (administrador)...").

### `confirmarDevolucion(Usuario, int $cvePrestamo): Prestamo`
- Solo préstamos `por_devolver` (422).
- Solo el encargado del bien o admin (403).
- `por_devolver → devuelto` + bitácora "Devolución confirmada"; el bien
  vuelve a `disponible` vía observer.

### `tienePrestamoActivo(int $cveBien): bool`
¿Existe préstamo en `ESTADOS_ACTIVOS` para el bien? Lo usa `solicitar`
y es la base de las reglas "bien ocupado".

### `bitacora(...)` (privado)
Busca `cve_motivo` por nombre en `motivo_de_movimiento` y crea el
registro (con `cve_persona_accion` = actor). Si el motivo no existe,
**no lanza** (no interrumpe la operación de negocio por un motivo
faltante en el catálogo).

## 5. Validación (StorePrestamoRequest)

| Campo | Reglas |
|---|---|
| `cve_bien` | required, integer, exists:bienes |
| `fecha_devolucion_pactada` | nullable, date, **after:now** (no fechas pasadas) |
| `observaciones` | nullable, string, max:500 |

## 6. Serialización

**PrestamoResumenResource** (historial): `cve_prestamo`,
`estado_prestamo`, `bien {cve_bien, identificador, nombre, encargado}`,
`solicitante {cve_persona, nombre}`. Suficiente para las tarjetas del
buzón de acciones.

**PrestamoResource** (detalle): lo anterior + `fecha_solicitud`,
`fecha_devolucion_pactada`, `fecha_devolucion_real` (datetime → string)
y `observaciones`.

## 7. Endpoints (PrestamoController)

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/prestamos?vista=&per_page=` | `listar` |
| GET | `/api/prestamos/{prestamo}` | `obtener` |
| POST | `/api/prestamos` | `solicitar` (201) |
| POST | `/api/prestamos/{prestamo}/aceptar` | `aceptar` |
| POST | `/api/prestamos/{prestamo}/devolver` | `devolver` |
| POST | `/api/prestamos/{prestamo}/confirmar-devolucion` | `confirmarDevolucion` |

`index` valida `vista` y `per_page` con 422 claro (sin coerción
silenciosa). El `message` de las mutaciones cambia según el estado
resultante (p. ej. "pendiente de confirmación del encargado").

## 8. Errores típicos

| Situación | Respuesta |
|---|---|
| Bien en papelera / prestado / sin encargado / propio | 422 |
| Ya tiene préstamo activo | 422 |
| Fecha pactada en el pasado | 422 (after:now) |
| Vista `general` sin ser admin | 403 |
| Aceptar/confirmar siendo ajeno | 403 |
| Devolver siendo ajeno | 403 |
| Préstamo/bien inexistente | 404 |
