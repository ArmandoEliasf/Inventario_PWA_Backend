# 10 — Observers y bitácora

El esquema original de producción usaba triggers de Postgres para
sincronizar estados y registrar movimientos. Este backend los reemplaza
con **Observers de Eloquent**, porque los tests corren sobre SQLite
(donde los triggers de Postgres no existen) y porque la lógica queda
versionada y testeada.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Observers/BienObserver.php` | Equivalente a `trg_log_estado_fisico` |
| `app/Observers/PrestamoObserver.php` | Equivalente a `trg_actualizar_estado_prestamo` |
| `app/Models/Bien.php` | `#[ObservedBy([BienObserver::class])]` |
| `app/Models/Prestamo.php` | `#[ObservedBy([PrestamoObserver::class])]` |
| `app/Models/BitacoraMovimiento.php` | Registro de la bitácora |
| `database/seeders/CatalogosSeeder.php` | Motivos extra necesarios |

## 2. bitacora_movimientos

Registro de todo lo que le pasa a un bien:

```php
cve_bien            FK nullable  ← null cuando el bien se purga (rastro)
cve_motivo          FK → motivo_de_movimiento
cve_persona_accion  FK → persona (quien hizo la acción)
fecha_movimiento    datetime     (cast 'datetime' en el modelo)
observaciones       text
```

Motivos en uso (catálogo `motivo_de_movimiento`):

| Motivo | Quién lo registra |
|---|---|
| `Préstamo` | PrestamosService: solicitud y aceptación |
| `Devolución` | PrestamosService: devolver y confirmar |
| `Baja Definitiva` | BienesService: papelera y purga |
| `Restauración` | BienesService: restaurar |
| `Revisión de Inventario` | BienObserver: cambio de estado físico |

Los tres últimos se agregan en `CatalogosSeeder` porque no existían en
el dump original.

## 3. BienObserver

Archivo: `app/Observers/BienObserver.php` — método `updated(Bien)`.

```php
if (! $bien->isDirty('estado_fisico')) return;   // solo si cambió el estado
$motivo = MotivoMovimiento where 'Revisión de Inventario' ?? 1;
BitacoraMovimiento::create([
    cve_bien, cve_motivo,
    cve_persona_accion => $bien->cve_encargado ?? 1,
    observaciones => "El estado físico cambió a: {estado}",
]);
```

- **Cuándo se dispara**: cualquier `save()` de un Bien que modifique
  `estado_fisico` — edición de bien, auditoría con estado distinto.
- Equivalente exacto del trigger `trg_log_estado_fisico`.
- Pendientes sugeridos: atribuir la bitácora al actor real (hoy usa el
  encargado del bien) y quitar los fallbacks `?? 1`.

## 4. PrestamoObserver

Archivo: `app/Observers/PrestamoObserver.php` — método
`updated(Prestamo)`.

```php
if (! $prestamo->isDirty('estado_prestamo')) return;
match (estado) {
  'aprobado'  → Bien.update(estado_prestamo = 'prestado'),
  'devuelto'  → Bien.update(estado_prestamo = 'disponible'),
}
```

- Equivalente del trigger `trg_actualizar_estado_prestamo`.
- `pendiente` y `por_devolver` NO cambian el bien (sigue
  'disponible'/'prestado' respectivamente).
- Usa `update()` directo (no `save()` de modelo) para no volver a
  disparar el BienObserver por un cambio ajeno.
- Caso especial cubierto en `PrestamosService::solicitar`: un préstamo
  creado directamente como 'aprobado' (admin) no pasa por `updated()`
  (el observer solo reacciona a cambios), por eso el service marca el
  bien a mano dentro de la misma transacción.

## 5. ¿Por qué saveQuietly / update?

| Técnica | Dónde | Por qué |
|---|---|---|
| `saveQuietly()` | `BienesService::crear` (asignar `codigo_qr` tras el insert) | no re-disparar el BienObserver |
| `update()` en query builder | PrestamoObserver, `solicitar` | evitar eventos de modelo en cascada |
| `DB::transaction` | crear/editar/prestar/etc. | atomicidad bien + bitácora |

## 6. Trazabilidad resultante

Con la combinación de observers + services, cada bien tiene un historial
completo consultable vía `GET /api/reportes/trazabilidad/{bien}`:

```
Solicitud de préstamo: Proyector Epson      (Préstamo)
Préstamo aprobado: Proyector Epson          (Préstamo)
Devolución registrada, pendiente de...      (Devolución)
Devolución confirmada: Proyector Epson      (Devolución)
El estado físico cambió a: regular          (Revisión de Inventario)
Bien enviado a papelera por admin           (Baja Definitiva)
Bien restaurado desde la papelera por...    (Restauración)
Bien eliminado definitivamente: ...         (Baja Definitiva, cve_bien null)
```
