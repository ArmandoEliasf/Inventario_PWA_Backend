# 08 — Módulo Reportes

Datos crudos para los formatos impresos que arma el frontend
(resguardos, movimientos internos, bajas, mantenimiento, trazabilidad y
estadísticas). Adaptado del controller original de Flight al patrón
Controller → Service. Contrato con ejemplos JSON:
`../docs/frontend-reportes.md`.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/ReportesService.php` | 554 líneas: 11 reportes públicos + helpers |
| `app/Http/Controllers/ReportesController.php` | HTTP + validación de query |
| `tests/Feature/ReportesTest.php` | 22 tests |

## 2. Reglas de acceso (todas en el service)

- `exigirConsulta`: Estudiante 403 en todos los reportes.
- `exigirPersonaPermitida`: reportes por persona — solo dueño o admin
  (403 "Solo puedes consultar reportes de tu propia persona.").
- Reportes generales: admin todo (+ filtro `?cve_adscripcion=`);
  profesor filtrado por `bienes.cve_encargado = actor`.
- `trazabilidad` y `movimientoInterno` reutilizan
  `BienesService::obtener` / `PrestamosService::obtener` (404/403 de
  propiedad).

## 3. Funciones, una por una

### `resguardoIndividual(Usuario, int $cvePersona, ?int $cveAula): array`
Bienes activos bajo responsabilidad de una persona: donde es
`cve_encargado` **o** donde tiene un préstamo `aprobado`
(`prestamos` LEFT JOIN con `fecha_devolucion_real` null). Campos por
bien: descripción, modelo, marca, no_serie, costo, no_factura,
`no_inventario` (= `codigo_barras`), estado físico y aula.

### `encabezadoReporte(Usuario, int $cvePersona): array`
Encabezado dinámico del formato: delega en `datosPersonaReporte`
(véase abajo). 404 si la persona no existe.

### `encabezadoDireccion(Usuario, int $cvePersona): array`
Solo `{nombre_area, abreviatura}` de la carrera (`persona.cve_carrera`
→ tabla `area`). Valores 'N/A' si no hay datos.

### `movimientoInterno(Usuario, int $cvePrestamo): array`
Formato de Movimiento Interno de un préstamo:
- `encabezado.baja`: quien entrega = encargado del bien
  (`datosPersonaBaja`; si el bien no tiene encargado, fallback al
  profesor responsable del aula vía `aula.cve_profesor`).
- `encabezado.alta`: quien recibe = solicitante del préstamo.
- Ambos con área/puesto/adscripción/folio SIIA/número de resguardo
  activos.
- `encabezado.no_resguardo_movimiento` = `cve_prestamo`;
  `motivo_movimiento` = estado del préstamo; `fecha_emision` = fecha
  de solicitud (`d/m/Y`).
- `relacion_bienes[]`: bien con `no_inventario` (= cve_bien),
  descripción, modelo, no_serie, marca, costo, factura y ubicación
  (`COALESCE(edificio || ' - ' || aula, aula)`; la concatenación se
  hace en SQL).

### `bienesBaja(Usuario, ?int $cveAdscripcion): array`
Bienes con `activo = false` (papelera) con ubicación
(`consultaBienesConUbicacion` con `$activos = false`).

### `bienesMantenimiento(Usuario, string $estadoFisico, ?int $cveAdscripcion): array`
Bienes activos con el estado físico dado (default
`'deteriorado'`; el estado `'reparacion'` del formato antiguo no existe
en este esquema).

### `trazabilidad(Usuario, int $cveBien): array`
Historial completo del bien desde `bitacora_movimientos` (con motivo y
persona que realizó la acción), ordenado por fecha desc. Devuelve el
bien resumido (`aula_actual`, estado físico), `total_movimientos` e
`historial[]` con `fecha`, `tipo_movimiento` (nombre del motivo),
`responsable` y `observaciones`.

### `valorPorAula(Usuario, ?int $cveAdscripcion): array`
`COUNT` + `SUM(COALESCE(costo_unitario,0))` de bienes activos agrupado
por aula, ordenado por valor desc. Devuelve `cve_aula`,
`nombre_aula`, `total_bienes` (int), `valor_total` (float).

### `prestamosNoDevueltos(Usuario, ?int $cveAdscripcion): array`
Préstamos en `PrestamosService::ESTADOS_ACTIVOS` (sin
`fecha_devolucion_real`) con bien, solicitante (nombre completo en PHP),
aula y edificio. Orden por `fecha_devolucion_pactada`.

### `conteoEstadoFisico` / `conteoEstadoPrestamo`
Delegan en `conteoPorCampo`: bienes activos agrupados por el campo
(`estado_fisico` / `estado_prestamo`) → `[{valor, total}]` con total
desc. Cualquier `valor` null del campo se respeta (aparece como null).

## 4. Helpers privados

### `datosPersonaReporte(object $persona): array`
Para los encabezados. Resuelve:
- `puesto`: `tipo_profesor.nombre` si la persona es profesor, si no
  "Personal".
- `area_departamento` + `area_abreviatura`: `profesor.cve_area` o, si
  no es profesor, `persona.cve_carrera` → tabla `area`.
- `clave_adscripcion` + `adscripcion`: primera adscripción activa de
  `adscripcion_persona` (orden de inserción).
- `siia`: última `folio_siia.clave_siia` con `estado = true`.
- `numero_resguardo`: último `numero_resguardo.no_resguardo` con
  `estado = true`.
- `nombre_completo` compuesto en PHP y `fecha_generacion` (now).

### `datosPersonaBaja(Bien): array`
Persona que entrega en el movimiento interno: encargado del bien;
fallback profesor del aula; si no hay ninguno → todo 'N/A'.

### `consultaBienesConUbicacion(...)`
Consulta común de bajas/mantenimiento: JOIN modelos/marcas/aula, filtro
por `activo`, por propiedad (no admin), por adscripción (admin) y por
estado físico opcional. Columnas: `cve_bien`, `descripcion_bien`,
`nombre_modelo`, `nombre_marca`, `no_serie`, `estado_fisico`,
`estado_prestamo`, `ultima_ubicacion` (aula), `fecha_alta`,
`observaciones` (descripcion del bien).

### `conteoPorCampo`, `fechaReporte(?string): ?string`
Conteo genérico y formateo `d/m/Y` con Carbon, null-safe (compatibilidad
Postgres/SQLite).

## 5. Validación (controller)

| Query | Reglas |
|---|---|
| `cve_aula` (resguardo) | nullable, integer, exists:aula |
| `cve_adscripcion` (reportes generales) | nullable, integer, exists:adscripcion |
| `estado_fisico` (mantenimiento) | nullable, in:bueno/regular/deteriorado (default 'deteriorado') |

## 6. Endpoints (ReportesController)

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/reportes/resguardo-individual/{persona}?cve_aula=` | `resguardoIndividual` |
| GET | `/api/reportes/encabezado-reporte/{persona}` | `encabezadoReporte` |
| GET | `/api/reportes/encabezado-direccion/{persona}` | `encabezadoDireccion` |
| GET | `/api/reportes/movimiento-interno/{prestamo}` | `movimientoInterno` |
| GET | `/api/reportes/bajas?cve_adscripcion=` | `bienesBaja` |
| GET | `/api/reportes/mantenimiento?estado_fisico=&cve_adscripcion=` | `bienesMantenimiento` |
| GET | `/api/reportes/trazabilidad/{bien}` | `trazabilidad` |
| GET | `/api/reportes/valor-por-aula?cve_adscripcion=` | `valorPorAula` |
| GET | `/api/reportes/prestamos-no-devueltos?cve_adscripcion=` | `prestamosNoDevueltos` |
| GET | `/api/reportes/conteo-estado-fisico?cve_adscripcion=` | `conteoEstadoFisico` |
| GET | `/api/reportes/conteo-estado-prestamo?cve_adscripcion=` | `conteoEstadoPrestamo` |

Respuestas: `{"data": {...}}` (arrays/objetos crudos, sin Resource,
porque el front arma los formatos).

## 7. Adaptaciones respecto al código Flight original

1. Los reportes generales aceptan `?cve_adscripcion=` (solo admin);
   antes siempre se filtraba por la adscripción del usuario.
2. Estados físicos válidos: `bueno/regular/deteriorado` (no existe
   `reparacion`).
3. Fechas formateadas en PHP (`d/m/Y`) para compatibilidad Postgres/SQLite.
4. El resguardo individual se basa en `bienes.cve_encargado` (modelo
   real), no en `aula.cve_profesor`.
5. Acceso por persona: solo dueño o admin (403).
