# 07 — Módulo Auditorías

Auditorías físicas del inventario con escaneo en campo: se crea una
auditoría, se escanean bienes con la cámara de la PWA y cada escaneo se
registra como detalle con `encontrado`, `estado_encontrado` y
observaciones de evidencia.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/AuditoriasService.php` | Reglas de negocio |
| `app/Http/Controllers/AuditoriaController.php` | HTTP |
| `app/Http/Requests/StoreAuditoriaRequest.php` | Creación de auditoría |
| `app/Http/Requests/StoreAuditoriaDetalleRequest.php` | Registro de escaneo |
| `app/Http/Requests/UpdateAuditoriaDetalleRequest.php` | Corrección de detalle |
| `app/Http/Resources/AuditoriaResource.php` | Auditoría (resumen/detalle) |
| `app/Http/Resources/AuditoriaDetalleResource.php` | Escaneo + `coincide_estado` |
| `tests/Feature/AuditoriasTest.php` | 16 tests |

## 2. Flujo de la PWA

```
1. POST /api/auditorias {observaciones_generales?}
        → el actor queda como auditor (cve_auditor)
2. Escanear código → GET /api/bienes/por-codigo/{codigo}
        → el front muestra la ficha del bien ("ver bien a detalle")
3. Confirmar → POST /api/auditorias/{id}/detalles
        {cve_bien, encontrado, estado_encontrado?, observaciones_evidencia?}
4. Si estado_encontrado difiere del estado_fisico del bien:
        el bien se actualiza → BienObserver deja bitácora
        "Revisión de Inventario" (equivalente a trg_log_estado_fisico)
```

## 3. Reglas de acceso

- Crear/listar/ver: Estudiante 403; auditor propietario o admin.
- Registrar/actualizar detalles: además de dueño de la auditoría, el
  bien debe ser accesible por el actor (`BienesService::obtener`:
  profesor solo bienes donde es encargado).
- Eliminar detalle: dueño de la auditoría o admin.

## 4. Funciones, una por una

### `listar(Usuario, int $perPage): LengthAwarePaginator`
Paginado con `auditor`, `withCount('detalles')` (→ `total_detalles` para
la UI de avance) y orden `cve_auditoria DESC`. Admin: todas;
profesor: solo las suyas (`cve_auditor`).

### `crear(Usuario, array $datos): Auditoria`
Estudiante 403. Crea con `fecha_auditoria = hoy`, el actor como auditor
y `observaciones_generales` opcional.

### `obtener(Usuario, int $cveAuditoria): Auditoria`
Detalle con `auditor`, `detalles.bien` y `withCount('detalles')`.
404 si no existe; 403 si no es el auditor ni admin.

### `registrarDetalle(Usuario, int $cveAuditoria, array $datos): AuditoriaDetalle`
1. `obtener` (dueño de la auditoría).
2. `bienes->obtener` (acceso al bien — 403 si ajeno).
3. **Sin duplicados**: si el bien ya está registrado en la auditoría →
   `ValidationException` → 422 (`cve_bien`).
4. En transacción: crea el detalle
   (`encontrado` default `true`, `estado_encontrado` y
   `observaciones_evidencia` opcionales) y sincroniza el bien
   (`sincronizarEstadoBien`).
5. Devuelve el detalle con `bien` cargado.

### `actualizarDetalle(Usuario, int $cveAuditoria, int $cveDetalle, array $datos): AuditoriaDetalle`
Corrige un detalle (p. ej. revisando la evidencia). Solo reemplaza los
campos presentes en `$datos` (`array_key_exists`, para poder poner
null). Tras guardar, vuelve a sincronizar el estado del bien con el
`estado_encontrado` resultante.

### `eliminarDetalle(Usuario, int $cveAuditoria, int $cveDetalle): void`
Retira un escaneo erróneo (delete físico del detalle). No revierte el
estado del bien (el cambio físico ya ocurrió).

### `sincronizarEstadoBien(Bien, ?string $estadoEncontrado)` (privado)
Si `estado_encontrado` no es null y difiere de `bien.estado_fisico`,
actualiza el bien. Ese `save()` dispara `BienObserver::updated` →
bitácora "Revisión de Inventario" con observaciones
"El estado físico cambió a: {estado}". Si el estado coincide, no toca
nada (no genera bitácora espuria).

## 5. Validación

### StoreAuditoriaRequest
| Campo | Reglas |
|---|---|
| `observaciones_generales` | nullable, string, max:1000 |

### StoreAuditoriaDetalleRequest
| Campo | Reglas |
|---|---|
| `cve_bien` | required, integer, exists:bienes |
| `encontrado` | nullable, boolean |
| `estado_encontrado` | nullable, in:bueno/regular/deteriorado |
| `observaciones_evidencia` | nullable, string, max:500 |

### UpdateAuditoriaDetalleRequest
Mismos campos con `sometimes` + `nullable` (permite limpiar valores).

## 6. Serialización

**AuditoriaResource**: `cve_auditoria`, `fecha_auditoria`,
`observaciones_generales`, `auditor {cve_persona, nombre}`,
`total_detalles` (con `withCount`) y `detalles[]` solo cuando están
cargados (detalle completo; en el listado no se cargan → resumen).

**AuditoriaDetalleResource**: `cve_auditoria_det`, `cve_auditoria`,
`encontrado` (bool), `estado_encontrado`, `observaciones_evidencia`,
`bien {cve_bien, identificador, nombre, estado_fisico, activo}` y
**`coincide_estado`** (bool): si `estado_encontrado === estado_fisico`
actual del bien — la PWA la usa para pintar en rojo los bienes cuyo
estado cambió durante la auditoría.

## 7. Endpoints (AuditoriaController)

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/auditorias?per_page=` | `listar` (resumen paginado) |
| POST | `/api/auditorias` | `crear` (201) |
| GET | `/api/auditorias/{auditoria}` | `obtener` (con detalles) |
| POST | `/api/auditorias/{auditoria}/detalles` | `registrarDetalle` (201) |
| PUT | `/api/auditorias/{auditoria}/detalles/{detalle}` | `actualizarDetalle` |
| DELETE | `/api/auditorias/{auditoria}/detalles/{detalle}` | `eliminarDetalle` |

`per_page` se clampa a 1–100 (sin 422 explícito en este controller).

## 8. Errores típicos

| Situación | Respuesta |
|---|---|
| Estudiante en cualquier acción | 403 |
| Auditoría ajena | 403 |
| Bien ajeno (profesor no encargado) | 403 |
| Bien repetido en la auditoría | 422 (`cve_bien`) |
| `estado_encontrado` inválido | 422 |
| Auditoría/detalle inexistente | 404 |

Pendientes sugeridos: índice único `(cve_auditoria, cve_bien)` en BD,
endpoint de "bienes pendientes de escanear" (progreso) y
`PUT /api/auditorias/{id}` para editar observaciones generales.
