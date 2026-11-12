# 02 — Base de datos

Origen: `database/bd-datos reales.sql` (dump PostgreSQL de la UTC). Portado a
migraciones Laravel, agnóstico de motor (corre hoy en SQLite, mañana en Postgres).

## Mapa de tablas

```
CATÁLOGOS NIVEL 0
├── roles                → usuarios
├── persona              → usuarios, profesor, alumnos, bienes(encargado), ...
├── area                 → area (auto-ref padre/hijos), profesor
├── tipo_profesor        → profesor
├── edificio             → aula
├── tipo_aula            → aula
├── tipos_bien           → familias_articulos
├── marcas               → modelos
├── motivo_de_movimiento → bitacora_movimientos
└── adscripcion          → aula, adscripcion_persona

REFERENCIAS NIVEL 1-2
├── profesor             (persona + tipo_profesor + area)
├── alumnos              (persona, matrícula)
├── aula                 (edificio + tipo_aula + profesor + adscripcion)
├── familias_articulos   (clave + tipos_bien)
├── modelos              (marcas)
├── articulos            (familias_articulos)  ← ~1,592 registros del clasificador
└── adscripcion_persona  (pivot adscripcion ↔ persona)

NÚCLEO: bienes
  FKs: modelos, articulos, aula, persona (encargado)
  UNIQUE: codigo_barras, nfc, codigo_qr, no_serie, no_factura
  estado_fisico:  bueno | regular | deteriorado
  estado_prestamo: disponible | prestado (sincronizado con prestamos)
  activo: false = papelera (soft delete de negocio)

OPERACIONES
├── auditorias           (persona auditor) → auditoria_detalle (bienes)
├── prestamos            (bienes + persona solicitante)
├── bitacora_movimientos (bienes + motivo + persona acción)
├── folio_siia           (persona)
└── numero_resguardo     (persona)
```

## Convenciones

- PKs: `cve_<entidad>` (bigint autoincremental). Eloquent mapeado con
  `protected $primaryKey`.
- Sin `timestamps` de Laravel salvo `usuarios` (mapea `fecha_registro`/
  `fecha_actualizacion` vía `CREATED_AT`/`UPDATED_AT`).
- FKs reales (`constrained()`) — el orden de migraciones garantiza dependencias.
- Índice dedicado `idx_bienes_qr` sobre `bienes.codigo_qr` (búsquedas por escaneo).

## Observers (reemplazo de triggers SQL)

| Trigger original | Observador | Comportamiento |
|---|---|---|
| `trg_actualizar_estado_prestamo` | `PrestamoObserver::updated` | préstamo `aprobado` → bien `prestado`; préstamo `devuelto` → bien `disponible` |
| `trg_log_estado_fisico` | `BienObserver::updated` | cambio de `estado_fisico` → insert automático en `bitacora_movimientos` (motivo "Revisión de Inventario", persona = encargado del bien) |

Detalle técnico: `PrestamoObserver` actualiza `bienes` con query builder
(`whereKey()->update()`), no con `Model::save()`, para no disparar eventos
del modelo `Bien` innecesariamente. `BienObserver` valida con `isDirty()`.

## Catálogo de roles

| cve_rol | nombre | descripción |
|---|---|---|
| 1 | Administrador | Acceso total al sistema |
| 2 | Director | Módulos de director y reportes generales (sin operaciones CRUD hoy) |
| 3 | Profesor | Operaciones sobre sus propios bienes |
| 4 | Estudiante | Sin acceso al inventario |

## Migrar a Supabase (pendiente)

1. Definir credenciales en `.env` (`DB_CONNECTION=pgsql`, host/puerto/usuario).
2. `php artisan migrate:fresh --seed` (las migraciones son portables).
3. Opcional: re-crear triggers SQL en el motor si se desea redundancia; con los
   observers ya no es necesario.
