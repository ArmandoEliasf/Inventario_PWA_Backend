# Notas para el frontend: endpoints de reportes

Adaptación del `ReportesController` anterior (Flight) a la API Laravel.
Todos los endpoints están protegidos con token Sanctum y devuelven
`{ "data": ... }`. El alumno (rol Estudiante) recibe `403` en todos.

## Cambios respecto al formato anterior (importante para el front)

- **Estados físicos válidos**: `bueno`, `regular`, `deteriorado`
  (no existe `reparacion`). El reporte de "mantenimiento" filtra por
  `estado_fisico` con default `deteriorado` y acepta el parámetro
  `?estado_fisico=` para otros estados.
- **Préstamos no devueltos**: incluye estados activos
  (`pendiente`, `aprobado`, `por_devolver`), no solo `aprobado`.
- **Filtro por adscripción**: `?cve_adscripcion=` disponible en los
  reportes generales, solo tiene efecto para el administrador.
- Las fechas de listados vienen formateadas `d/m/Y`; los encabezados
  traen `fecha_generacion` en `Y-m-d H:i:s`.
- Los reportes "por persona" (resguardo, encabezados) solo los puede
  pedir el propio dueño o el administrador (403 si el profesor pide
  otra persona).

## Endpoints

### 1. Resguardo individual
```
GET /api/reportes/resguardo-individual/{cve_persona}?cve_aula=X
```
Bienes activos de la persona (encargado o con préstamo aprobado). Cada fila:
`cve_bien, descripcion_bien, nombre_modelo, nombre_marca, no_serie,
costo_unitario, no_factura, no_inventario (codigo_barras), estado_fisico,
cve_aula, nombre_aula`. Ordenado por aula y descripción.

### 2. Encabezado de reporte
```
GET /api/reportes/encabezado-reporte/{cve_persona}
```
`cve_persona, nombre, apellido_paterno, apellido_materno, nombre_completo,
puesto, area_departamento, area_abreviatura, clave_adscripcion,
adscripcion, siia, numero_resguardo, fecha_generacion`.
Los campos sin datos llegan como `"N/A"` / `"Personal"`.

### 3. Encabezado de dirección
```
GET /api/reportes/encabezado-direccion/{cve_persona}
```
`{ nombre_area, abreviatura }` (carrera desde `persona.cve_carrera`).

### 4. Movimiento interno (formato completo por préstamo)
```
GET /api/reportes/movimiento-interno/{cve_prestamo}
```
```json
{
  "encabezado": {
    "nombre_baja": "...", "area_baja": "...", "adscripcion_baja": "...",
    "siia_baja": "...", "resguardo_baja": "...",
    "nombre_alta": "...", "area_alta": "...", "adscripcion_alta": "...",
    "siia_alta": "...", "resguardo_alta": "...",
    "no_resguardo_movimiento": 12, "motivo_movimiento": "aprobado",
    "observaciones_movimiento": "...", "fecha_emision": "15/11/2026"
  },
  "relacion_bienes": [{
    "no_inventario": 12, "descripcion": "...", "modelo": "...",
    "no_serie": "...", "marca": "...", "costo": 1500.5,
    "factura": "...", "ubicacion": "Edificio A - Lab-1"
  }]
}
```
- Baja = encargado del bien (si no tiene, el profesor del aula).
- Alta = persona que solicita el préstamo.
- Acceso: solicitante, encargado del bien o admin (igual que el detalle
  del préstamo).

### 5. Bienes dados de baja (papelera)
```
GET /api/reportes/bajas
```
Filas: `cve_bien, descripcion_bien, nombre_modelo, nombre_marca, no_serie,
estado_fisico, estado_prestamo, ultima_ubicacion, fecha_alta, observaciones`.
Admin: todas (filtro `?cve_adscripcion=`); profesor: solo las suyas.

### 6. Bienes en mantenimiento
```
GET /api/reportes/mantenimiento?estado_fisico=deteriorado
```
Mismo formato que bajas, pero bienes activos con el estado indicado.

### 7. Trazabilidad de un bien
```
GET /api/reportes/trazabilidad/{cve_bien}
```
```json
{
  "bien": { "cve_bien": 1, "nombre": "...", "no_serie": "...",
             "aula_actual": "A-101", "estado_fisico": "bueno" },
  "total_movimientos": 3,
  "historial": [{ "fecha": "...", "tipo_movimiento": "Préstamo",
                  "responsable": "...", "observaciones": "..." }]
}
```
El historial viene de la bitácora (préstamos, devoluciones, papelera,
cambios de estado físico). Acceso: encargado del bien o admin.

### 8. Valor monetario por aula
```
GET /api/reportes/valor-por-aula
```
`cve_aula, nombre_aula, total_bienes, valor_total` (desc por valor).
Admin: todas las aulas; profesor: solo sus bienes.

### 9. Préstamos no devueltos
```
GET /api/reportes/prestamos-no-devueltos
```
`cve_prestamo, fecha_solicitud, fecha_devolucion_pactada (d/m/Y),
estado_prestamo, cve_bien, nombre_bien, no_serie, persona_nombre,
nombre_aula, nombre_edificio`. Admin: todos; profesor: sus bienes o
sus propias solicitudes.

### 10. Conteos para gráficas
```
GET /api/reportes/conteo-estado-fisico
GET /api/reportes/conteo-estado-prestamo
```
`[{ "valor": "bueno", "total": 40 }, ...]` sobre bienes activos.

## Errores

| Código | Causa |
|--------|-------|
| 401    | Sin token |
| 403    | Alumno, profesor accediendo a datos ajenos |
| 404    | Persona/bien/préstamo inexistente |
| 422    | Parámetro inválido (`cve_aula`, `cve_adscripcion`, `estado_fisico`) |
