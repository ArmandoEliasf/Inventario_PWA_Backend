# Documentación del Backend — Inventario PWA

API Laravel para la PWA de inventario de la carrera DSM. Este documento
resume TODO lo implementado en el proyecto: módulos, reglas de negocio,
endpoints, cambios realizados y estado de los tests.

- Frontend: proyecto aparte (`FRONTEND_URL`), contratos en
  `docs/frontend-escaner-auditorias.md` y `docs/frontend-reportes.md`.
- Stack: Laravel (Sanctum para auth) + PostgreSQL (Supabase). Los tests
  corren sobre SQLite en memoria.

---

## 1. Arquitectura

```
app/
├── Http/Controllers/   AuthController, AulaController, BienController,
│                       PrestamoController, AuditoriaController,
│                       ReportesController, InformacionController
├── Http/Requests/      Validación de payloads (Store/Update Bien/Prestamo/Auditoria*)
├── Http/Resources/     Serialización (Bien*, Prestamo*, Auditoria*)
├── Models/             Eloquent sobre el esquema real (tablas en español)
├── Observers/          PrestamoObserver, BienObserver
└── Services/           BienesService, PrestamosService, CodigoBarrasService,
                        AuditoriasService, ReportesService, FotoService,
                        InformacionService
```

Patrón aplicado en todos los módulos: **Controller (HTTP) → Service
(reglas de negocio) → Resource (JSON)**.

---

## 2. Autenticación (Sanctum)

| Endpoint | Descripción |
|----------|-------------|
| `POST /api/login` (throttle 10/min) | `usuario` + `contrasena`. Devuelve `token` + `{cve_usuario, nombre, rol}` |
| `POST /api/logout` | Revoca el token actual |
| `GET /api/me` | Datos del usuario + bloque `persona` con `matricula` (si es alumno) |

- Tokens expiran en 480 min (`SANCTUM_TOKEN_EXPIRATION`).
- Roles: `Administrador`, `Profesor`, `Estudiante`, `Director` (y otros).
  La PWA ajusta la interfaz con el `rol` del login.

---

## 3. Módulo Bienes

Reglas (todas en `BienesService`):

- **Estudiante**: bloqueado del inventario (403) en todo.
- **Profesor**: solo ve/edita/envía a papelera bienes donde es
  `cve_encargado`.
- **Administrador**: acceso total; puede asignar encargado.

### Creación
- Identificador de **7 dígitos** (`0000001`), único global, se guarda en
  `no_serie` (UNIQUE en BD).
- Código de barras autogenerado: `{TIPO}-{FAMILIA}-{ARTICULO}-{identificador}`
  (`CodigoBarrasService`).
- `codigo_qr = bienes/{cve_bien}` tras el insert; la URL completa para el
  QR impreso es `{FRONTEND_URL}/bienes/{id}` (`urlQr`).
- Profesor queda como encargado de lo que crea.

### Papelera
- `DELETE /api/bienes/{id}` → `activo = false` + bitácora "Baja Definitiva".
- Solo bienes `disponible` (sin préstamo activo).
- `PUT /api/bienes/{id}/restaurar` → bitácora "Restauración".
- `DELETE /api/bienes/{id}/purgar` → borrado definitivo desde la papelera:
  borra auditoría detalle, préstamos y bitácora del bien, y deja rastro con
  `cve_bien = null`.

### Listados
- `GET /api/bienes?vista=general|papelera&cve_aula=&cve_edificio=&per_page=`
- `GET /api/aulas` → aulas con `total_bienes` para las pestañas por aula
  (profesor: sus aulas; admin: todas, con filtro por edificio).

### Fotos (Google Drive)

- `POST /api/bienes` acepta campo opcional `foto` (multipart, image,
  jpg/jpeg/png/webp, máx 5 MB) al registrar el bien.
- `POST /api/bienes/{bien}/foto` sube o reemplaza la foto (encargado o
  admin); borra la anterior del disco.
- `GET /api/bienes/{bien}/foto` es el **endpoint proxy** que streamea la
  imagen respetando la autorización por rol/propiedad (Estudiante 403).
  Es la URL que la PWA debe usar para mostrar la foto
  (`BienResource.foto_url`, disponible también `tiene_foto` para la UI).
- Almacenamiento: disco configurable con `PHOTOS_DISK` en `.env`
  (`local` para dev/tests, `google` = Google Drive con las credenciales
  `GOOGLE_DRIVE_*`; driver registrado en `AppServiceProvider` con
  `masbug/flysystem-google-drive-ext`). La ruta del archivo queda en
  `bienes.foto_drive_id` y el enlace público de Drive (si aplica) en
  `bienes.foto_url`.
- `DELETE /api/bienes/{bien}/purgar` también borra el archivo de foto.

---

## 4. Módulo Préstamos

Flujo de estados: `pendiente → aprobado → por_devolver → devuelto`.

| Acción | Quién | Regla |
|--------|-------|-------|
| Solicitar | Cualquiera | Bien activo, disponible, con encargado ≠ solicitante, sin préstamo activo. Admin queda auto-aprobado |
| Aceptar (`pendiente → aprobado`) | Encargado del bien o admin | Bien pasa a `prestado` (observer) |
| Devolver (`aprobado → por_devolver`) | Solo el solicitante | Admin la registra directo como `devuelto` |
| Confirmar devolución (`por_devolver → devuelto`) | Encargado o admin | Bien vuelve a `disponible` |

- Vistas del listado: `solicitados` (propios), `recibidos` (bienes donde
  soy encargado), `general` (solo admin).
- Bitácora con motivos "Préstamo" y "Devolución".
- `fecha_devolucion_pactada` debe ser futura (`after:now`).

---

## 5. Escaneo QR / código de barras → detalle del bien

```
GET /api/bienes/por-codigo/{codigo}
```

Resuelve **cualquiera** de estas tres formas de escaneo:
1. URL del QR (`http://<FRONTEND_URL>/bienes/12`)
2. Código de barras (`EQ-01-0021-0000007`)
3. Identificador / `no_serie` (`0000007`)

Devuelve el mismo detalle que `GET /api/bienes/{id}` (`BienResource` +
`qr_url`). Es la función que llama la PWA al leer un código y abrir la
vista "ver bien a detalle".

Guía de instalación del escáner (PWA): `npm install html5-qrcode`,
componente listo para copiar y flujo completo en
`docs/frontend-escaner-auditorias.md`.

---

## 6. Módulo Auditorías

Flujo de la PWA:
1. `POST /api/auditorias` → crea la auditoría (el actor queda como auditor).
2. Escanear bien → `GET /api/bienes/por-codigo/{codigo}` → detalle.
3. Confirmar → `POST /api/auditorias/{id}/detalles`.

Endpoints:

| Endpoint | Descripción |
|----------|-------------|
| `GET /api/auditorias` | Resumen paginado (`total_detalles`). Admin: todas; profesor: suyas |
| `POST /api/auditorias` | Crea auditoría (observaciones generales opcionales) |
| `GET /api/auditorias/{id}` | Detalle con todos los bienes escaneados |
| `POST /api/auditorias/{id}/detalles` | Registra bien escaneado: `cve_bien`, `encontrado`, `estado_encontrado`, `observaciones_evidencia` |
| `PUT /api/auditorias/{id}/detalles/{det}` | Corrige un detalle |
| `DELETE /api/auditorias/{id}/detalles/{det}` | Retira un escaneo |

Reglas:
- Sin duplicados: el mismo bien no puede registrarse dos veces (422).
- El profesor solo registra bienes donde es encargado (403 si no).
- Si `estado_encontrado` difiere del `estado_fisico` del bien, se
  sincroniza y el `BienObserver` deja bitácora "Revisión de Inventario"
  (equivalente al trigger `trg_log_estado_fisico`).
- `data.detalles[].coincide_estado` sirve para pintar diferencias en la UI.

---

## 7. Módulo Reportes (adaptado del controller Flight)

Datos crudos para los formatos que arma el frontend. Acceso: Estudiante
403; profesor limitado a lo suyo; admin todo. Contrato completo con
ejemplos en `docs/frontend-reportes.md`.

| Endpoint | Contenido |
|----------|-----------|
| `GET /api/reportes/resguardo-individual/{persona}?cve_aula=` | Bienes de una persona (encargado o con préstamo aprobado) |
| `GET /api/reportes/encabezado-reporte/{persona}` | Nombre, puesto, área, adscripción, folio SIIA, resguardo |
| `GET /api/reportes/encabezado-direccion/{persona}` | `{nombre_area, abreviatura}` |
| `GET /api/reportes/movimiento-interno/{prestamo}` | Baja (entrega), alta (recibe) + relación de bienes con ubicación |
| `GET /api/reportes/bajas` | Bienes con `activo=false` |
| `GET /api/reportes/mantenimiento?estado_fisico=` | Activos con el estado dado (default `deteriorado`) |
| `GET /api/reportes/trazabilidad/{bien}` | Historial desde bitácora |
| `GET /api/reportes/valor-por-aula` | SUM de costo agrupado por aula |
| `GET /api/reportes/prestamos-no-devueltos` | Préstamos en estados activos sin devolver |
| `GET /api/reportes/conteo-estado-fisico` | `[{valor, total}]` |
| `GET /api/reportes/conteo-estado-prestamo` | `[{valor, total}]` |

Adaptaciones respecto al código original:

- Los reportes generales aceptan `?cve_adscripcion=` (solo tiene efecto
  para admin); antes se filtraba siempre por la adscripción del usuario.
- Estados físicos válidos: `bueno/regular/deteriorado` (no existe
  `reparacion`; el formato anterior lo usaba para "mantenimiento").
- Fechas formateadas en PHP (`d/m/Y` en listados) para ser compatible
  con Postgres y con los tests en SQLite.
- El resguardo individual se basa en `bienes.cve_encargado` (modelo real
  de este backend), no en `aula.cve_profesor`.
- Acceso por persona: solo dueño o admin (403 si un profesor pide la de
  otra persona).

---

## 8. Observers y bitácora

| Observer | Equivalente trigger | Acción |
|----------|--------------------|--------|
| `BienObserver` (updated) | `trg_log_estado_fisico` | Bitácora "Revisión de Inventario" al cambiar `estado_fisico` |
| `PrestamoObserver` (updated) | `trg_actualizar_estado_prestamo` | Bien → `prestado` cuando el préstamo se aprueba; `disponible` cuando se devuelve |

Motivos de bitácora en uso: `Préstamo`, `Devolución`, `Baja Definitiva`,
`Restauración`, `Revisión de Inventario`. Los tres últimos se agregan en
`CatalogosSeeder` (no existían en el dump original; "Revisión de
Inventario" se agregó en esta implementación porque el observer caía al
fallback con motivo incorrecto).

---

## 9. Base de datos

- **Migraciones** (espejan el esquema real de producción):
  - `0001_01_01_*`: roles, persona, usuarios, password_reset_tokens, sessions, cache, jobs, personal_access_tokens.
  - `2025_01_01_000001`: catálogos (area, tipo_profesor, edificio, tipo_aula, tipos_bien, marcas, motivo_de_movimiento, adscripcion).
  - `2025_01_01_000002`: organización (profesor, alumnos, aula, adscripcion_persona).
  - `2025_01_01_000003`: inventario (familias_articulos, modelos, articulos, bienes).
  - `2025_01_01_000004`: operaciones (auditorias, auditoria_detalle, prestamos, bitacora_movimientos, folio_siia, numero_resguardo).
- **Seeders**: `CatalogosSeeder` (catálogos completos + motivos extra) y
  `InventarioDemoSeeder` (100 bienes en aulas DSM, préstamos, bitácora,
  auditorías, resguardos y folios SIIA).
- `database/bd-datos reales.sql` — dump de referencia de producción.
-Usuarios demo de los tests: 1=Administrador (persona 1), 2=Director
(persona 2), 3=Profesor (persona 3), 4=Estudiante (persona 8).

---

## 10. Cambios y correcciones realizados en esta sesión

### Funcionalidad nueva
1. **Escáner**: endpoint `GET /api/bienes/por-codigo/{codigo}` (service
   `obtenerPorCodigo` en `BienesService`), acepta URL de QR, código de
   barras o identificador.
2. **Módulo de auditorías completo** (§6): service, controller, 3
   FormRequests, 2 Resources, rutas y 16 tests.
3. **Módulo de reportes completo** (§7): service, controller, 11 rutas
   y 22 tests, adaptado del controller Flight.
4. **`AuthController::me`**: ahora devuelve bloque `persona` con nombre
   completo y `matricula` (antes cargaba la relación sin usarla).
5. **Documento para el frontend**: `docs/frontend-escaner-auditorias.md`
   (instalación de `html5-qrcode`, componente de escáner, flujo) y
   `docs/frontend-reportes.md` (contrato de reportes).
6. **Fotos de bienes en Google Drive** (§3 "Fotos"): `FotoService`,
   campos `foto` en alta, endpoints `POST/GET /api/bienes/{bien}/foto`,
   proxy de lectura con autorización y 15 tests (`BienesFotosTest`).
   `.env.example` completo (estaba vacío).
7. **Módulo de información** (§13): `InformacionService` +
   `InformacionController`, 17 endpoints de catálogos/datos auxiliares
   para rellenar formularios automáticamente y 25 tests
   (`InformacionTest`).
8. **CORS para la PWA** (§14): `config/cors.php` con origen desde
   `FRONTEND_URL`/`FRONTEND_URLS`, sin credenciales (token Bearer) y
   preflight cacheado; 3 tests (`CorsTest`).

### Correcciones
1. **`BienObserver` movido** de `app/Services/` a `app/Observers/`: su
   namespace era `App\Observers` pero el archivo estaba en otra carpeta,
   lo que rompía el autoload del `#[ObservedBy]` (76 tests fallaban).
2. **Motivo "Revisión de Inventario" agregado** al `CatalogosSeeder`;
   el `BienObserver` lo busca y antes caía al fallback `?? 1`
   ("Reparación"), dejando bitácora con motivo incorrecto.
3. **`StorePrestamoRequest`**: `fecha_devolucion_pactada` ahora con
   `after:now` (antes aceptaba fechas pasadas).
4. **`PrestamoController::index`**: `vista` y `per_page` validados con
   422 claro (antes coerción silenciosa de `per_page=abc` a 1).
5. **Código muerto eliminado**: `CodigoBarrasService::existe()` no se
   usaba en ningún lugar.
6. **Cast `fecha_movimiento` => datetime** en `BitacoraMovimiento`
   (necesario para la trazabilidad y consistente con los otros modelos).

### Pendiente sugerido (no implementado aún)
- Race conditions: locks (`lockForUpdate`) en `PrestamosService::solicitar`
  y unicidad de identificador en `BienesService::crear/editar`.
- Índice único `(cve_auditoria, cve_bien)` en `auditoria_detalle`.
- Motivo semánticamente correcto para "enviar a papelera" (usa
  "Baja Definitiva" aunque es reversible).
- `BienObserver` atribuye la bitácora al `cve_encargado` del bien, no al
  actor que hizo el cambio.
- Endpoint de "bienes pendientes de escanear" en una auditoría (progreso).
- `PUT /api/auditorias/{id}` para editar observaciones generales.

---

## 11. Tests

```bash
cd backend
php artisan test
```

- **161 tests / 804 aserciones, todos en verde.** (ahora 164 / ~816 con
  CorsTest)
- Suite: `AuthTest`, `BienesCrudTest`, `BienesVistasTest`,
  `BienesPorCodigoTest`, `BienesFotosTest`, `PrestamosTest`,
  `PrestamosHistorialTest`, `AuditoriasTest`, `ReportesTest`,
  `InformacionTest` (+ Example tests).
- Corren sobre **SQLite en memoria** con `RefreshDatabase` + seeders
  completos en cada test (`phpunit.xml`).

---

## 12. Seguridad

- Autenticación Sanctum con expiración (480 min) y throttle en login.
- `.env` NO está en git (`.gitignore` correcto) y contiene credenciales
  reales de Supabase: si alguna vez se compartió, rotarlas.
- Las reglas de propiedad (profesor/encargado) se aplican en los
  services, no en los controllers, de forma uniforme.

---

## 13. Módulo Información (catálogos para formularios)

Datos complementarios para que la PWA rellene formularios automáticamente
(selects en cascada, dropdowns de bienes, personal, etc.). Adaptado del
`InformacionController` Flight al patrón Controller → Service. Respuesta
con envelope `{data: ...}`. Service: `InformacionService`.

| Endpoint | Contenido |
|----------|-----------|
| `GET /api/aulas/edificio/{edificio}` | Aulas (id + nombre) de un edificio |
| `GET /api/informacion/aulas` | Aulas con edificio, tipo, capacidad y adscripción |
| `GET /api/informacion/personal/carrera/{carrera}` | Personas (id + nombre completo) de una carrera (área) |
| `GET /api/informacion/profesores` | Profesores activos (id + nombre completo) |
| `GET /api/informacion/bienes?cve_aula=` | Catálogo de bienes (id + nombre) para dropdowns |
| `GET /api/informacion/bienes/disponibles?cve_aula=` | Bienes activos y disponibles (para solicitar préstamo) |
| `GET /api/informacion/tipos-bien` | Catálogo de tipos de bien |
| `GET /api/informacion/familias/tipo/{tipo}` | Familias de un tipo de bien |
| `GET /api/informacion/articulos/familia/{familia}` | Artículos de una familia |
| `GET /api/informacion/edificios` | Edificios activos |
| `GET /api/informacion/tipos-aula` | Catálogo de tipos de aula |
| `GET /api/informacion/marcas` | Catálogo de marcas |
| `GET /api/informacion/modelos` | Catálogo de modelos (con marca y descripción) |
| `GET /api/informacion/bien/qr/{bien}` | `codigo_qr` + `qr_url` del bien (404 si no existe) |
| `GET /api/informacion/valor-bienes-cargo/{aula}` | Bienes del aula agrupados por nombre + `total_bienes` + `monto_total` |
| `GET /api/informacion/valor-bienes-cargo-detalle/{aula}` | Bienes individuales con `no_serie`, costo, `tiene_foto`/`foto_url` |
| `GET /api/informacion/bienes-por-aula/{aula}` | Bienes del aula con marca, modelo, encargado, estados y foto |

Reglas de acceso:

- Catálogos puros, aulas, profesores, personal y QR de un bien:
  cualquier usuario autenticado.
- `bienes` (catálogo), `valor-bienes-cargo*` y `bienes-por-aula`:
  Estudiante 403; profesor limitado a los bienes donde es `cve_encargado`
  (modelo de propiedad de este backend; el original usaba
  `aula.cve_profesor`); admin todo.
- `bienes/disponibles` abierto a todos los autenticados porque el
  Estudiante también solicita préstamos.
- `GET /api/informacion/aulas` filtra por la adscripción activa del actor
  (igual que el controller original); usuario sin adscripción ve todas.
- Los nombres completos se componen en PHP (compatible Postgres/SQLite).

---

## 14. CORS (diseñado para la PWA)

Configuración en `config/cors.php` (Laravel 12 incluye `HandleCors` en el
middleware global, no hay que registrar nada). 3 tests en `CorsTest`.

| Decisión | Valor | Motivo |
|----------|-------|--------|
| `paths` | `api/*`, `up` | Solo la API JSON y el health check |
| `allowed_origins` | `FRONTEND_URL` + `FRONTEND_URLS` (coma) + `localhost:5173`/`127.0.0.1:5173` solo en local/testing | Única PWA; varios builds sin tocar código; comodidad en dev |
| `allowed_methods` | `*` | La API usa GET/POST/PUT/DELETE |
| `allowed_headers` | `*` | `Authorization` (token) y `Content-Type` (JSON/multipart de foto) |
| `supports_credentials` | `false` | La PWA usa token Bearer, nunca cookies |
| `max_age` | `3600` | Cachea el preflight: una petición OPTIONS menos por llamada |

Regla de seguridad: **nunca `*` en producción** — con tokens Bearer un
origen abierto deja consumir la API desde cualquier sitio. Para
subdominios usar `allowed_origins_patterns`.
