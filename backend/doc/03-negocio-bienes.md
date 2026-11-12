# 03 — Reglas de negocio: Bienes

## Identificador y no_serie

El **identificador** que captura el usuario ES el `no_serie` del bien.

- Formato: exactamente **7 dígitos numéricos** (`regex /^[0-9]{7}$/`), se guarda
  como **string** para conservar ceros iniciales (`0000001`).
- La columna `no_serie` es UNIQUE en el esquema, por lo tanto el identificador es
  **único globalmente** entre todos los bienes (no solo por artículo).
- El campo NO se exponga como `no_serie` en la API: `BienResource` lo devuelve
  como `identificador`.
- Al crear y al editar, el servicio sincroniza `no_serie = identificador`.

## Código de barras

Formato: `{TIPO}-{FAMILIA}-{ARTICULO}-{IDENTIFICADOR}`

Ejemplo: `MO-MD-1583-0000001`
- `MO` → clave de `tipos_bien` (Muebles de Oficina en General)
- `MD` → clave de `familias_articulos` (Muebles de Oficina en General)
- `1583` → clave de `articulos`
- `0000001` → identificador del usuario (7 dígitos)

Reglas:
- El usuario solo captura el identificador; el prefijo se deriva del artículo
  seleccionado (`CodigoBarrasService::construir()`).
- Unicidad garantizada por `bienes.codigo_barras UNIQUE`.
- Al editar: si cambia el artículo, el prefijo TIPO-FAMILIA-ARTICULO se
  re-genera y el identificador se conserva.

## Código QR

- Generación automática al crear el bien: en BD se guarda `bienes/{cve_bien}`.
- La URL completa se compone en runtime: `{FRONTEND_URL}/{codigo_qr}` —
  ej. `http://localhost:5173/bienes/5`.
- Al escanear el QR, la PWA abre la pantalla del bien (deep link).
- Cambiar de dominio solo requiere editar `FRONTEND_URL` en `.env`; los QR
  almacenados no se regeneran.

## Papelera (eliminación)

No hay borrado físico. `DELETE /api/bienes/{id}`:

1. El bien debe estar `estado_prestamo = 'disponible'` (un bien prestado no se
   puede enviar a papelera → 422).
2. `activo` pasa a `false`.
3. Se registra un movimiento en `bitacora_movimientos` con motivo
   **"Baja Definitiva"** y la persona que ejecutó la acción.

## Estados físicos

| Valor | Uso |
|---|---|
| `bueno` | default al crear |
| `regular` | desgaste visible |
| `deteriorado` | requiere atención |

Cualquier cambio de `estado_fisico` genera bitácora automática (ver
[02-base-de-datos.md](02-base-de-datos.md), observers).

## Estados de préstamo

| Valor en bienes | Significado |
|---|---|
| `disponible` | Puede asignarse a un préstamo (default) |
| `prestado` | Tiene un préstamo `aprobado` activo |

Otros estados de la tabla `prestamos`: `pendiente`, `por_devolver`, `devuelto`.
La sincronización la realiza `PrestamoObserver`.
