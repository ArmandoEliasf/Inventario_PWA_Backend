# Backend Inventario PWA — Documento integral

> Resumen completo de todo lo implementado hasta la fecha: base de datos,
> catálogos, CRUD de bienes y autenticación. Documentación extendida por tema
> en `doc/` y bitácora cronológica en `md/README.md`.

---

## 1. Contexto general

| Aspecto | Valor |
|---|---|
| Proyecto | Módulo de inventario para una PWA (solo backend) |
| Framework | Laravel 12, PHP 8.2+ |
| BD desarrollo | SQLite (`database/database.sqlite`) |
| BD producción | PostgreSQL vía Supabase (conectado por pooler, ver §4.1) |
| Origen del esquema | `database/bd-datos reales.sql` (dump PostgreSQL con datos reales UTC) |
| Testing | PHPUnit (25 tests / 83 aserciones, todos en verde) |
| Cliente de pruebas | Bruno (colección pendiente) |

El backend consume las cuentas y el catálogo existentes; el registro de
usuarios lo hace el área de programación directamente en la BD (no hay endpoint
de registro).

---

## 2. Base de datos

### 2.1 Esquema (24 tablas, portado del dump a migraciones Laravel)

**Catálogos base:** `roles`, `persona`, `usuarios`, `area` (auto-referenciada),
`tipo_profesor`, `edificio`, `tipo_aula`, `tipos_bien`, `marcas`,
`motivo_de_movimiento`, `adscripcion`

**Organización:** `profesor`, `alumnos`, `aula`, `adscripcion_persona`

**Clasificador:** `familias_articulos`, `modelos`, `articulos` (~1,592 del
clasificador UTC), y la tabla central **`bienes`**.

**Operaciones:** `auditorias`, `auditoria_detalle`, `prestamos`,
`bitacora_movimientos`, `folio_siia`, `numero_resguardo`

### 2.2 Convenciones

- PKs con nombre `cve_<entidad>` → `$table->id('cve_...')`.
- Campos únicos en `bienes`: `codigo_barras`, `nfc`, `codigo_qr`, `no_serie`, `no_factura`.
- Índice `idx_bienes_qr` sobre `codigo_qr` (búsquedas por escaneo).
- FKs reales con orden de migración garantizado.
- Sin `timestamps` Laravel salvo `usuarios` (mapea `fecha_registro`/`fecha_actualizacion`).

### 2.3 Modelos (24)

Modelos Eloquent con `table`/`primaryKey` explícitos y `$fillable` completo.
`Usuario` extiende `Illuminate\Foundation\Auth\User`:

- `getAuthPassword()` devuelve `contrasena_hash` (bcrypt, compatible con `Hash::check`).
- Trait `HasApiTokens` (Sanctum).
- `CREATED_AT`/`UPDATED_AT` mapeados a `fecha_registro`/`fecha_actualizacion`.

Relaciones implementadas: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`
(`Persona::adscripciones()` con pivot `adscripcion_persona`), auto-referencia
(`Area::padre()/hijos()`).

### 2.4 Observers (reemplazo de triggers PL/pgSQL)

| Trigger original | Implementación Laravel |
|---|---|
| `trg_actualizar_estado_prestamo` | `PrestamoObserver::updated` — préstamo `aprobado` → bien `prestado`; `devuelto` → `disponible` |
| `trg_log_estado_fisico` | `BienObserver::updated` — cambio de `estado_fisico` → registro en `bitacora_movimientos` (motivo "Revisión de Inventario") |

Se registran con el atributo `#[ObservedBy(...)]` en los modelos (Laravel 12).
La lógica vive en la capa de aplicación: funciona igual en SQLite y PostgreSQL.

---

## 3. Datos y seeders

Los datos del dump se extrajeron a `database/seeders/data/catalogos.php`
(parser PHP del SQL: maneja `''` como escape, subselects, y descarta las filas
del bloque `DO` con variables PL/pgSQL).

**`CatalogosSeeder`**: catálogos en orden de dependencias; resuelve subselects
por lookup en vivo; genera los `INSERT ... SELECT` como código (profesores,
alumnos, vinculaciones adscripción↔persona).

**`InventarioDemoSeeder`**: replica el script de presentación —
100 bienes en las 6 aulas de DSM, préstamos (18), bitácora (8), 2 auditorías,
100 resguardos (`RGTO-NNNN-YYYY`), 100 folios SIIA (`SIIA-NNNN`).

```bash
php artisan migrate:fresh --seed   # reconstruir todo
```

**Usuarios de prueba** (del dump, contraseña `password`):

| usuario | cve_usuario | rol |
|---|---|---|
| admin | 1 | Administrador |
| director | 2 | Director |
| profesor | 3 | Profesor |
| alumno | 4 | Estudiante |

---

## 4. Autenticación (Sanctum)

- `laravel/sanctum` v4.4 + tabla `personal_access_tokens`.
- `POST /api/login` → `{"usuario": "...", "contrasena": "..."}` → devuelve
  `{ token, usuario: { cve_usuario, nombre, rol } }`. Con `throttle:10,1`.
  Rechaza usuarios inexistentes, inactivos o contraseña inválida (422).
- `GET /api/me` → perfil autenticado.
- `POST /api/logout` → revoca el token actual.
- Rutas protegidas con `auth:sanctum`; la PWA envía
  `Authorization: Bearer <token>` en cada petición.

### 4.1 Expiración de tokens

- `config/sanctum.php` publicado (Laravel 12 no lo genera por defecto).
- `expiration` = `env('SANCTUM_TOKEN_EXPIRATION', 480)` → los tokens viven
  **8 horas**; configurable desde `.env`.
- Sanctum valida que `created_at` esté dentro de la ventana; tokens con
  `expires_at` propio también se respetan si se definieran.
- Test: `test_token_expira_pasada_la_ventana_configurada` atrasa `created_at`
  481 minutos y verifica 401.

### 4.2 Variables de entorno

| Variable | Uso |
|---|---|
| `DB_CONNECTION/DB_HOST/...` | Conexión a Supabase por pooler (`pgsql`, host `aws-1-<region>.pooler.supabase.com`, usuario `postgres.<ref>`, `DB_SSLMODE=require`) |
| `SANCTUM_TOKEN_EXPIRATION` | Ventana de validez del token en minutos |
| `SUPABASE_URL` | Base URL de la Data API del proyecto |
| `SUPABASE_PUBLISHABLE_KEY` | Clave pública (solo cliente, limitada por RLS del proyecto) |
| `SUPABASE_SECRET_KEY` | Clave server-side; nunca exponer al frontend |
| `SUPABASE_JWKS_URL` | JWKS de Auth (uso futuro) |

---

## 5. CRUD de bienes

### 5.1 Pautas de negocio

| Regla | Definición |
|---|---|
| Código de barras | `{TIPO}-{FAMILIA}-{ARTICULO}-{IDENTIFICADOR}` — ej. `MO-MD-1583-0000001`. El usuario solo captura el identificador; el prefijo se deriva del artículo. |
| Identificador | Exactamente 7 dígitos numéricos, guardado como **string** (conserva ceros iniciales). **Llena la columna `no_serie`**, que es UNIQUE → unicidad global. La API lo expone como `identificador`, nunca como `no_serie`. |
| Código QR | Automático al crear: en BD se guarda `bienes/{cve_bien}`; la URL completa se arma en runtime con `FRONTEND_URL` del `.env` (ej. `http://localhost:5173/bienes/5`). Cambiar de dominio no requiere regenerar nada. |
| Al cambiar el artículo | El prefijo del código de barras se re-genera conservando el identificador. |
| Papelera | `DELETE` = `activo=false` (nunca borrado físico). Bloqueado si el bien está `prestado`. Registra bitácora con motivo "Baja Definitiva". Desde la papelera: `PUT .../restaurar` reactiva (motivo "Restauración") y `DELETE .../purgar` borra físicamente (borra dependencias y deja rastro en bitácora con `cve_bien = null`). |
| Estados físicos | `bueno` (default), `regular`, `deteriorado` |
| Estados de préstamo | `disponible` ↔ `prestado` (sincronizado por observer) |

### 5.2 Permisos

| Acción | Alumno | Profesor | Administrador |
|---|:---:|:---:|:---:|
| Consultar inventario | ❌ | ✅ solo propios | ✅ todos |
| Crear bienes | ❌ | ✅ | ✅ |
| Editar bienes | ❌ | ✅ solo propios | ✅ todos |
| Enviar a papelera | ❌ | ✅ solo propios | ✅ todos |
| Registrar usuarios | — | — | — (directo en BD por Programación) |

- Bien "propio" del profesor = `cve_encargado` apunta a su `cve_persona`.
- Al crear, el profesor queda automáticamente como encargado; el admin puede
  asignar cualquier encargado.
- La verificación de propiedad está centralizada en `BienesService::obtener()`.

### 5.3 Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/login` | Login → token |
| GET | `/api/me` | Perfil autenticado |
| POST | `/api/logout` | Cerrar sesión |
| GET | `/api/aulas?cve_edificio=X` | Pestañas "por aula" con conteo (profesor: sus aulas; admin: todas) |
| GET | `/api/bienes?vista=general\|papelera&cve_aula=X&cve_edificio=X` | Listado resumido paginado (filtrado por rol) |
| GET | `/api/bienes/{id}` | Detalle completo + `qr_url` + foto + código de barras |
| POST | `/api/bienes` | Crear |
| PUT/PATCH | `/api/bienes/{id}` | Editar |
| DELETE | `/api/bienes/{id}` | Papelera |
| PUT | `/api/bienes/{id}/restaurar` | Restaurar de la papelera (bitácora "Restauración") |
| DELETE | `/api/bienes/{id}/purgar` | Borrado definitivo (con rastro en bitácora) |

### 5.4 Vistas del listado de bienes

Tres vistas para profesor, dos para admin, resueltas con parámetros del mismo
endpoint (no hay rutas aparte):

| Vista | Parámetro | Profesor | Administrador |
|---|---|---|---|
| General | `vista=general` (default) | sus bienes activos | todos los bienes activos |
| Por aula | `+ cve_aula=X` | sus bienes de esa aula (pestañas desde `GET /api/aulas`) | bienes de esa aula |
| Por edificio | `+ cve_edificio=X` | — | bienes de todas las aulas del edificio |
| Papelera | `vista=papelera` | su papelera | toda la papelera |

Dos niveles de información: el listado (`BienResumenResource`) entrega solo
`cve_bien`, `identificador`, `nombre`, `encargado` y `estado_fisico`; el detalle
(`BienResource`) entrega todo, incluidos `foto_url`, `foto_drive_id` y
`codigo_barras`, más `qr_url` calculada.

### 5.5 Arquitectura del módulo

```
routes/api.php
  → BienController (delgado: resuelve actor, delega)
    → FormRequests (validación)
    → BienesService (reglas de negocio, permisos, transacciones)
      → CodigoBarrasService (armado y unicidad del código)
      → Modelos + Observers (efectos: bitácora, estado de préstamos)
    → BienResource (serialización JSON)
```

Configuración: `FRONTEND_URL` en `.env` y `config/services.php`.

---

## 5bis. Módulo de préstamos

Flujo: `pendiente → aprobado → por_devolver → devuelto` (ver detalle en
`doc/05-api.md`). Reglas acordadas:

- Cualquier rol puede solicitar cualquier bien **con encargado**.
- El encargado no puede solicitar su propio bien (422).
- Solicitud: bien activo, `disponible` y sin otro préstamo activo.
- `aprobado`: lo marca el encargado del bien o un admin. Un admin que
  solicita queda **auto-aprobado**.
- `por_devolver`: el solicitante registra la devolución; el bien sigue
  `prestado` hasta la confirmación.
- `devuelto`: lo confirma el encargado o un admin. Un admin que devuelve
  va directo a `devuelto` (sin confirmación del dueño).
- El bien queda `disponible` al finalizar; `prestado` desde la aprobación
  (`PrestamoObserver`).
- Bitácora: motivos "Préstamo" y "Devolución" ("Devolución" se agregó al
  `CatalogosSeeder`, no existía en el dump).

Archivos: `PrestamosService` (reglas + transacciones + bitácora),
`PrestamoController`, `StorePrestamoRequest`, `PrestamoResource` (bien
resumen + solicitante), rutas `/api/prestamos*`.

---

## 6. Testing

`php artisan test` → **66 tests, 280 aserciones**.

- `tests/Feature/AuthTest.php` (7): login correcto (token+rol), contraseña
  incorrecta, usuario inexistente, usuario inactivo, consumo de endpoint con
  Bearer token, revocación en logout y expiración del token por ventana
  configurada.
- `tests/Feature/BienesCrudTest.php` (19): formato de códigos, encargado
  automático, identificador (7 dígitos, ceros, unicidad global), bloqueos de
  alumno, visibilidad por propiedad, edición con re-generación de prefijo,
  papelera con bitácora, bienes prestados, acceso ajeno (403), sin token (401).
- `tests/Feature/BienesVistasTest.php` (19): listado resumido (campos exactos),
  vista papelera (admin/profesor, solo bienes inactivos y solo propios),
  filtro por aula, filtro por edificio (admin), endpoint `/api/aulas`
  (aulas del profesor con conteos, filtro por edificio), bloqueo de alumno,
  detalle con foto y código de barras, restaurar y purgar (permisos,
  validaciones, borrado físico con dependencias y rastro en bitácora).
- `tests/Feature/PrestamosTest.php` (21): flujo completo alumno→profesor
  (pendiente, aceptación, bien `prestado`, devolución `por_devolver`,
  confirmación `devuelto`, bien `disponible`), auto-aprobado y auto-devolución
  del admin, bloqueos (bien prestado/en papelera/propio/duplicado, estados
  inválidos), permisos (403) y listados `solicitados`/`recibidos` con
  visibilidad por rol.

Convención: `RefreshDatabase` + seed completo en cada test; asserts de negocio
sobre modelos (`refresh()`), no solo sobre el JSON de respuesta.

---

## 7. Pendientes

- [x] Conectar Supabase — conexión configurada por pooler en `.env` (ver §4.2).
- [ ] Colección de Bruno (login + CRUD bienes).
- [ ] Módulo de préstamos (solicitud, aprobación, devolución).
- [ ] Módulo de auditorías.
- [ ] Catálogos (CRUD admin: artículos, familias, aulas, etc.).

> Nota: `app/Models/User.php` fue eliminado en el commit
> `c07a28b` ("eliminación de user.php"); el auth usa `App\Models\Usuario`.
