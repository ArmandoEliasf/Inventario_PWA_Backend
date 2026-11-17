# 02 — Base de datos

24 tablas del esquema real (en español), espejadas por 9 migraciones en
`database/migrations/`. Motor: PostgreSQL (Supabase) en producción,
SQLite en memoria para tests. Dump de referencia:
`database/bd-datos reales.sql`.

## 1. Migraciones

| Archivo | Crea |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `roles`, `persona`, `usuarios`, `password_reset_tokens`, `sessions` |
| `2025_01_01_000001_create_catalogos_base_table.php` | `area`, `tipo_profesor`, `edificio`, `tipo_aula`, `tipos_bien`, `marcas`, `motivo_de_movimiento`, `adscripcion` |
| `2025_01_01_000002_create_organizacion_table.php` | `profesor`, `alumnos`, `aula`, `adscripcion_persona` |
| `2025_01_01_000003_create_inventario_table.php` | `familias_articulos`, `modelos`, `articulos`, `bienes` |
| `2025_01_01_000004_create_operaciones_table.php` | `auditorias`, `auditoria_detalle`, `prestamos`, `bitacora_movimientos`, `folio_siia`, `numero_resguardo` |
| `0001_01_01_000001/000002` (Laravel base) | `cache`, `jobs`, `personal_access_tokens` |

## 2. Tablas y columnas clave

### Identidad y acceso

**roles** — `cve_rol` (PK), `nombre`. Valores sembrados:
`Administrador`, `Director`, `Profesor`, `Estudiante`, ...

**persona** — `cve_persona` (PK), `nombre`, `apellido_paterno`,
`apellido_materno`, `cve_carrera` (FK → `area`), `cve_nivel_estudio`,
`fecha_alta`. El nombre completo se compone en PHP (ver cada service).

**usuarios** — `cve_usuario` (PK), `usuario` (único), `contrasena_hash`
(bcrypt), `cve_rol` (FK → roles), `nombre`, `email`, `activo`,
`cve_persona` (FK → persona), `fecha_registro`, `fecha_actualizacion`.

### Catálogos

| Tabla | Columnas clave |
|---|---|
| `area` | `cve_area`, `cve_area_padre` (FK → area), `abreviatura` (5), `nombre` (60), `activo` |
| `tipo_profesor` | `cve_tipo_profesor`, `nombre`, `descripcion` |
| `edificio` | `cve_edificio`, `nombre`, `abreviatura`, `activo` (default true) |
| `tipo_aula` | `cve_tipo_aula`, `nombre` |
| `tipos_bien` | `cve_tipo`, `clave` (2), `nombre` |
| `marcas` | `cve_marca`, `nombre_marca` (único) |
| `motivo_de_movimiento` | `cve_motivo`, `nombre_motivo`, `requiere_aprobacion` |
| `adscripcion` | `cve_adscripcion`, `clave_adscripcion` (único), `nombre_adscripcion`, `estado`, `fecha_registro` |

### Organización

**profesor** — `cve_profesor` (PK), `cve_persona` (FK), `cve_tipo_profesor` (FK), `cve_area` (FK nullable), `activo`.

**alumnos** — `cve_alumno`, `cve_persona` (FK), `matricula` (15),
`fecha_inscrito`, `inscrito`, `activo`.

**aula** — `cve_aula`, `cve_edificio` (FK), `cve_tipo_aula` (FK),
`cve_profesor` (FK nullable → profesor), `cve_adscripcion` (FK nullable),
`nombre` (30), `codigo_barras`, `codigo_qr` (únicos), `capacidad`.

**adscripcion_persona** — `cve_adscripcion_persona`, `cve_adscripcion` (FK),
`cve_persona` (FK), `activo`, `fecha_registro`. Pivot de personas con
adscripciones.

### Inventario (jerarquía tipo → familia → artículo → bien)

| Tabla | Columnas clave | FK |
|---|---|---|
| `familias_articulos` | `cve_familia`, `clave` (2), `nombre` | `cve_tipo` → tipos_bien |
| `modelos` | `cve_modelo`, `nombre_modelo`, `descripcion` | `cve_marca` → marcas |
| `articulos` | `cve_articulo`, `clave` (4, único), `nombre` | `cve_familia` → familias_articulos |
| `bienes` | ver abajo | |

**bienes** — corazón del sistema:

```
cve_bien              PK
nombre                varchar(100)
codigo_barras         varchar(50)  UNIQUE nullable   ← {TIPO}-{FAM}-{ART}-{ID}
nfc                   varchar(50)  UNIQUE nullable
codigo_qr             varchar(255) UNIQUE nullable   ← 'bienes/{cve_bien}' (index)
no_serie              varchar(50)  UNIQUE nullable   ← el identificador de 7 dígitos
no_factura            varchar(50)  UNIQUE nullable
descripcion           text nullable
foto_url              varchar(500) nullable            ← URL pública de Drive (si aplica)
foto_drive_id         varchar(255) nullable            ← ruta del archivo en el disco
cve_modelo            FK nullable → modelos
cve_articulo          FK nullable → articulos
costo_unitario        decimal(10,2) nullable
cve_aula              FK nullable → aula
cve_encargado         FK nullable → persona           ← propiedad del bien
estado_fisico         varchar(20) nullable             ← bueno | regular | deteriorado
estado_prestamo       varchar(20) default 'disponible' ← disponible | prestado
activo                boolean default true             ← false = papelera
fecha_registro        date default CURRENT_DATE
```

### Operaciones

**auditorias** — `cve_auditoria`, `fecha_auditoria`, `cve_auditor`
(FK → persona), `observaciones_generales` (text nullable).

**auditoria_detalle** — `cve_auditoria_det`, `cve_auditoria` (FK),
`cve_bien` (FK), `encontrado` (bool), `estado_encontrado` (nullable),
`observaciones_evidencia` (text nullable).

**prestamos** — `cve_prestamo`, `cve_bien` (FK),
`cve_persona_solicita` (FK → persona), `fecha_solicitud`,
`fecha_devolucion_pactada` (nullable, debe ser futura),
`fecha_devolucion_real` (nullable), `estado_prestamo`
(`pendiente|aprobado|por_devolver|devuelto`), `observaciones` (500).

**bitacora_movimientos** — `cve_movimiento`, `cve_bien` (FK nullable —
se pone a null al purgar un bien), `cve_motivo` (FK),
`cve_persona_accion` (FK → persona), `fecha_movimiento` (datetime),
`observaciones` (text).

**folio_siia** — `cve_folio`, `cve_persona` (FK), `clave_siia`,
`estado` (bool). Claves SIIA activas por persona (reportes).

**numero_resguardo** — `cve_resguardo_pk`, `cve_persona` (FK),
`no_resguardo`, `estado` (bool). Números de resguardo activos por persona.

### Infraestructura Laravel

`personal_access_tokens` (Sanctum: token de la PWA, `tokenable` →
usuarios, `created_at` es la referencia para la expiración),
`sessions`, `cache`, `jobs`, `password_reset_tokens`.

## 3. Estados y sus transiciones

```
bienes.estado_fisico:    bueno ⇄ regular ⇄ deteriorado
                         (cambia por auditoría o edición; el cambio
                          queda en bitácora vía BienObserver)

bienes.estado_prestamo:  disponible ──(préstamo aprobado)──▶ prestado
                         prestado ──(devuelto)──▶ disponible
                         (sincronizado por PrestamoObserver;
                          'prestado' nunca cambia a mano)

prestamos.estado_prestamo: pendiente → aprobado → por_devolver → devuelto
                           (admin: solicitud nace 'aprobado' y puede
                            registrar la devolución directo 'devuelto')

bienes.activo:           true ⇄ false (papelera reversible)
                         purga = DELETE físico + bitácora con cve_bien null
```

## 4. Integridad sin triggers

El esquema original de producción tenía triggers
(`trg_log_estado_fisico`, `trg_actualizar_estado_prestamo`). Este backend
los reemplaza con Observers (ver
[10-observadores-y-bitacora.md](10-observadores-y-bitacora.md)), porque
los tests corren sobre SQLite (donde los triggers de Postgres no existen).

Pendiente sugerido: índice único `(cve_auditoria, cve_bien)` en
`auditoria_detalle` (la regla de duplicados hoy vive solo en el service).

## 5. Seeders

- `CatalogosSeeder`: catálogos completos del dump + motivos extra
  (`Restauración`, `Devolución`, `Revisión de Inventario`).
- `InventarioDemoSeeder`: 100 bienes en aulas DSM, préstamos, bitácora,
  auditorías, resguardos y folios SIIA.

Detalles en [13-datos-y-seeders.md](13-datos-y-seeders.md).
