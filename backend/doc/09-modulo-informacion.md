# 09 — Módulo Información

Catálogos y datos auxiliares para que la PWA **rellene formularios
automáticamente**: selects en cascada (edificio → aula, tipo → familia →
artículo), dropdowns de bienes, personal, y valores por aula. Adaptado
del `InformacionController` Flight al patrón Controller → Service.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/InformacionService.php` | 17 métodos públicos + helpers |
| `app/Http/Controllers/InformacionController.php` | HTTP + validación |
| `tests/Feature/InformacionTest.php` | 25 tests |

## 2. Reglas de acceso

- **Catálogos puros** (edificios, tipos, familias, artículos, marcas,
  modelos), **organización** (aulas, profesores, personal) y **QR de un
  bien**: cualquier usuario autenticado.
- **Listas de bienes**: Estudiante 403; profesor solo bienes donde es
  `cve_encargado`; admin todo.
- **Excepción**: `bienes/disponibles` abierto a todos los autenticados
  porque el Estudiante también solicita préstamos y necesita elegir un
  bien.
- `GET /api/informacion/aulas` se filtra por la **adscripción activa del
  actor** (`adscripcion_persona.activo = true`), como en el controller
  original; usuario sin adscripción ve todas.

## 3. Funciones, una por una

### Aulas y organización

- **`aulasPorEdificio(int $cveEdificio): array`** — Aulas
  (`cve_aula`, `nombre`) de un edificio para el select en cascada.
  Sin control de rol (no expone datos de inventario).
- **`aulas(Usuario $actor): array`** — Aulas con `nombre_edificio`,
  `tipo_aula`, `capacidad` y `nombre_adscripcion`
  (COALESCE → "Sin asignar"). Filtra por la adscripción activa del actor
  si la tiene.
- **`personalPorCarrera(int $cveCarrera): array`** — Personas de una
  carrera (área) con `nombre_completo` compuesto en PHP.
- **`profesores(): array`** — Profesores activos (JOIN `profesor`)
  con `nombre_completo`, para elegir encargado/solicitante.

### Bienes para selects

- **`bienesCatalogo(Usuario, ?int $cveAula): array`** — Catálogo
  (`cve_bien`, `nombre`) de bienes activos. Admin: todos; profesor:
  solo los suyos; Estudiante 403. Filtro opcional por aula.
- **`bienesDisponibles(?int $cveAula): array`** — Bienes activos con
  `estado_prestamo = 'disponible'`, para el dropdown de solicitud de
  préstamos. Incluye `no_serie`, `codigo_qr`, `nombre_modelo`,
  `nombre_marca` y `nombre_encargado`. Sin restricción de rol.

### Catálogos

- **`tiposBien()`** — `cve_tipo, clave, nombre` de `tipos_bien`.
- **`familiasPorTipo(int $cveTipo)`** — Familias con su `cve_tipo`
  (cascada tipo → familia).
- **`articulosPorFamilia(int $cveFamilia)`** — Artículos con su
  `cve_familia` (cascada familia → artículo; el artículo alimenta
  el código de barras del bien).
- **`edificios()`** — Edificios `activo = true` con `abreviatura`.
  (El controller Flight reintentaba sin el filtro si la columna no
  existía; aquí la columna existe en el esquema, no hay hack.)
- **`tiposAula()`**, **`marcas()`**, **`modelos()`** — Catálogos
  simples; modelos incluye `cve_marca` y `descripcion` para filtrar
  modelos por marca en cascada en el front.

### QR y valores por aula

- **`qrDeBien(int $cveBien): array`** — `cve_bien`, `nombre`,
  `codigo_qr` y `qr_url` (`{FRONTEND_URL}/{codigo_qr}`). 404 si el bien
  no existe.
- **`valorBienesCargo(Usuario, int $cveAula): array`** — Bienes del
  aula **agrupados por nombre** con `cantidad` y `costo_total`, más
  `total_bienes` (suma de cantidades) y `monto_total` (redondeado a 2).
  Estudiante 403; profesor solo sus bienes del aula.
- **`valorBienesCargoDetalle(Usuario, int $cveAula): array`** — Lo
  mismo **sin agrupar**: `cve_bien`, `descripcion_bien`, `no_serie`,
  `costo_unitario` (redondeado), `tiene_foto` y `foto_url` (endpoint
  proxy). Nunca expone `foto_drive_id`.
- **`bienesPorAula(Usuario, int $cveAula): array`** — Bienes del aula
  con `codigo_barras`, `no_serie`, `estado_fisico`,
  `estado_prestamo`, `modelo`, `marca`, `nombre_encargado` (compuesto
  en PHP) y foto. Reglas de propiedad idénticas a `valorBienesCargo`.
  Nota: el controller Flight filtraba por `aula.cve_profesor`; este
  backend usa el modelo real de propiedad (`cve_encargado`).

### Helpers privados

- `esAdmin` / `exigirConsulta` — delegan en `BienesService` (misma
  autorización que el resto del sistema).
- `nombreCompleto(object $persona): string` — nombre + apellidos con
  `trim(implode(' ', array_filter(...)))` (compatible Postgres/SQLite).

## 4. Validación (controller)

| Query | Reglas |
|---|---|
| `cve_aula` (bienes / bienes/disponibles) | nullable, integer, exists:aula,cve_aula → 422 si inválida |

Los parámetros de ruta llevan `whereNumber` (un entero distinto → 404 de ruta).

## 5. Endpoints

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/aulas/edificio/{edificio}` | `aulasPorEdificio` |
| GET | `/api/informacion/aulas` | `aulas` |
| GET | `/api/informacion/personal/carrera/{carrera}` | `personalPorCarrera` |
| GET | `/api/informacion/profesores` | `profesores` |
| GET | `/api/informacion/bienes?cve_aula=` | `bienesCatalogo` |
| GET | `/api/informacion/bienes/disponibles?cve_aula=` | `bienesDisponibles` |
| GET | `/api/informacion/tipos-bien` | `tiposBien` |
| GET | `/api/informacion/familias/tipo/{tipo}` | `familiasPorTipo` |
| GET | `/api/informacion/articulos/familia/{familia}` | `articulosPorFamilia` |
| GET | `/api/informacion/edificios` | `edificios` |
| GET | `/api/informacion/tipos-aula` | `tiposAula` |
| GET | `/api/informacion/marcas` | `marcas` |
| GET | `/api/informacion/modelos` | `modelos` |
| GET | `/api/informacion/bien/qr/{bien}` | `qrDeBien` |
| GET | `/api/informacion/valor-bienes-cargo/{aula}` | `valorBienesCargo` |
| GET | `/api/informacion/valor-bienes-cargo-detalle/{aula}` | `valorBienesCargoDetalle` |
| GET | `/api/informacion/bienes-por-aula/{aula}` | `bienesPorAula` |

Respuestas: `{"data": [...]}` en todos los casos (listas o arrays de
objetos). Son lecturas sin paginación — son catálogos pequeños
destinados a selects.

## 6. Uso típico en la PWA (cascadas)

```
Formulario "Registrar bien":
  edificios → (elige edificio) → aulas/edificio/{id} → aula
  tipos-bien → familias/tipo/{id} → articulos/familia/{id} → artículo
               (el artículo determina el prefijo del código de barras)
  marcas → modelos filtrados en el front por cve_marca
  (identificador de 7 dígitos: lo escribe el usuario o sugiere el front
   con el siguiente consecutivo disponible)

Formulario "Solicitar préstamo" (estudiante incluido):
  bienes/disponibles?cve_aula={aula_actual} → elegir bien

Formulario "Movimiento interno":
  profesores → encargado; valor-bienes-cargo/{aula} → totales del aula
```
