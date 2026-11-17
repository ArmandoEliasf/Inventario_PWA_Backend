# 13 — Datos y seeders

## 1. Origen de los datos

`database/bd-datos reales.sql` es el dump de referencia de producción.
Las migraciones espejan ese esquema y `CatalogosSeeder` inserta el
contenido de `database/seeders/data/catalogos.php` (datos extraídos del
SQL original).

## 2. CatalogosSeeder

`database/seeders/CatalogosSeeder.php`. Inserta en orden (respetando
FKs):

| Bloque | Contenido |
|---|---|
| roles | Administrador, Director, Profesor, Estudiante, ... |
| tipos_bien | Equipo (EQ), Herramienta, ... con `clave` de 2 |
| area | Áreas/carreras con `abreviatura` |
| tipo_profesor | tipos con descripción |
| motivo_de_movimiento | del dump **+ 3 extra** (ver §4) |
| edificio / tipo_aula / adscripcion | catálogos completos |
| marcas | marcas del dump |
| persona / usuarios | 25 personas + usuarios demo |
| familias_articulos | resuelven `cve_tipo` vía lookup |
| articulos | resuelven `cve_familia` vía lookup |
| modelos | con marca |
| aula | resuelven `cve_adscripcion` vía lookup |
| profesor | personas 1–5 y 16–20 (tipo 1, área 1) |
| alumnos | personas 6–15 y 21–25, matrícula `MAT-XXXX` |
| adscripcion_persona | vínculos por nombre del SQL |

`resolverLookup()` soporta los subselects del SQL original
(`'__raw' => "(SELECT ...)"`) resolviéndolos con consultas a la BD —
mantiene el seeder compatible con SQLite.

## 3. InventarioDemoSeeder

`database/seeders/InventarioDemoSeeder.php`. Datos demo realistas:

- **100 bienes** distribuidos en las aulas `A-101, A-102, Lab-1, Lab-2,
  CC-1, CC-2` (por rangos de `rn` 1–100, como en el SQL).
- Préstamos en distintos estados, registros de bitácora, auditorías con
  detalles, números de resguardo y folios SIIA.
- Los bienes usan identificadores de 7 dígitos (`0000001`...), con
  código de barras y QR consistentes con las reglas de negocio.

## 4. Motivos extra del seeder (no existían en el dump)

| Motivo | Quién lo usa | Por qué se agregó |
|---|---|---|
| `Restauración` | `BienesService::restaurar` | bitácora de la papelera |
| `Devolución` | `PrestamosService::devolver/confirmar` | módulo de préstamos |
| `Revisión de Inventario` | `BienObserver` | sin él, el observer caía al fallback `?? 1` ("Reparación") y dejaba bitácora con motivo incorrecto |

## 5. Usuarios demo (contraseña `password`)

| Usuario | cve_usuario | Rol | Persona |
|---|---|---|---|
| admin | 1 | Administrador | 1 (Juan Pérez García) |
| director | 2 | Director | 2 (María López Martínez) |
| profesor | 3 | Profesor | 3 (Carlos Sánchez Rodríguez) |
| alumno | 4 | Estudiante | 8 (Sofía Hernández Ríos) |

Los tests los toman así (`Usuario::find(1/2/3/4)`), con la excepción de
que la persona del alumno es la 8.

## 6. Comandos

```bash
php artisan migrate:fresh --seed     # reconstruir BD con datos demo
php artisan db:seed                  # solo seeders (BD ya migrada)
php artisan migrate:fresh --seed     # en Postgres: apunta a Supabase según .env
```

Cuidado: `migrate:fresh` **borra todas las tablas**. En producción solo
con `--force` y revisando `.env`.
