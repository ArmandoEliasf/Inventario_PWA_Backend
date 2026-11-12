# 06 — Datos y seeders

## Origen de los datos

El dump `database/bd-datos reales.sql` contiene el esquema PostgreSQL + datos
reales del clasificador de la UTC. Los INSERTs "plano" se extrajeron con un
script parser (temporario, no versionado) a:

```
database/seeders/data/catalogos.php   # arrays PHP con var_export
```

Contenido extraído (filas): `area` 15, `tipo_profesor` 15, `roles` 4,
`persona` 27, `usuarios` 11, `edificio` 25, `tipo_aula` 15, `tipos_bien` 2,
`marcas` 25, `motivo_de_movimiento` 15, `adscripcion` 38,
`familias_articulos` 16, `modelos` 25, `articulos` 1592, `aula` 27.

Detalle técnico del parser:
- Tokenizador de filas que respeta comillas (`''` como escape), paréntesis
  anidados y subselects `(SELECT cve_... FROM ...)` (marcados como `__raw`).
- Los INSERT del bloque `DO $$...$$` con variables PL/pgSQL se descartan
  (valores marcados `__ident`).
- Los INSERT tipo `SELECT ... WHERE ... random()` se replican en el seeder con
  generación **determinista** (mismo resultado en cada seed, sin aleatorio).

## CatalogosSeeder

1. Inserta catálogos base en orden de dependencias (roles → tipos → familias
   → artículos → ... → aulas).
2. Resuelve los subselects `__raw` con lookups en vivo:
   - familias: `tipos_bien WHERE clave = ?`
   - artículos: `familias_articulos WHERE clave = ?`
   - aulas: `adscripcion WHERE clave_adscripcion = ?`
3. Genera los `INSERT ... SELECT` del dump:
   - `profesor`: personas 1–5 y 16–20 (tipo 1, área 1)
   - `alumnos`: personas 6–15 y 21–25, matrícula `MAT-XXXX`
4. `adscripcion_persona`: 9 grupos de vinculaciones persona ↔ adscripción UTC
   (DSM `73113100`, Contaduría `73112100`, etc.).

## InventarioDemoSeeder

Replica el "script de presentación" del dump con 100 bienes de DSM:

- Distribución de aulas: A-101 (1–17), A-102 (18–34), Lab-1 (35–51),
  Lab-2 (52–68), CC-1 (69–84), CC-2 (85–100).
- Encargados: 1–20 admin, 21–40 director, 41–60 profesor, 61–80 alumno,
  85–100 Jorge Rivas (DSM).
- Códigos: `BAR-NNN`, `NFC-NNN`, `QR-NNN`, `SN-NNN-NNN`.
- `estado_fisico`: `regular` si `rn % 5 == 0`, si no `bueno`.
- Bienes `prestado` en los rangos del dump (1–4, 22–25, 41–44, 61–62, 85–86),
  con préstamos `aprobado` + 2 `pendiente` → total 18 préstamos.
- Bitácora (8 movimientos), 2 auditorías, 100 resguardos (`RGTO-NNNN-YYYY`),
  100 folios SIIA (`SIIA-NNNN`).

## Comandos

```bash
php artisan migrate:fresh --seed   # reconstruye todo (BD demo completa)
php artisan db:seed --class=CatalogosSeeder   # solo catálogos
```

## Usuarios demo

Contraseña (hash bcrypt) para todos: `password`

| usuario | cve_usuario | cve_persona | rol |
|---|---|---|---|
| admin | 1 | 1 | Administrador |
| director | 2 | 2 | Director |
| profesor | 3 | 3 | Profesor |
| alumno | 4 | 8 | Estudiante |
| jorge.rivas | 5 | 16 | Profesor (DSM) |
| diana.vargas ... alumno | 6–11 | 17–22 | Profesor/Estudiante |
