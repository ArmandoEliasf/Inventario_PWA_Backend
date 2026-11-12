# 01 — Arquitectura

## Stack

| Capa | Tecnología |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| BD local (desarrollo) | SQLite (`database/database.sqlite`) |
| BD objetivo (producción) | PostgreSQL 18 vía Supabase (pendiente de conectar) |
| Testing | PHPUnit 11 |
| Estilo de código | Laravel Pint |
| Cliente de pruebas de API | Bruno (colección pendiente) |

## Estructura relevante

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/BienController.php   # capa de endpoints (delgada)
│   │   ├── Requests/Store|UpdateBienRequest.php  # validación de entrada
│   │   └── Resources/BienResource.php       # serialización de salida
│   ├── Models/                              # 24 modelos Eloquent (cve_* PK)
│   ├── Observers/                           # lógica que antes vivía en triggers SQL
│   └── Services/                            # lógica de negocio
│       ├── BienesService.php
│       └── CodigoBarrasService.php
├── database/
│   ├── migrations/                          # 4 migraciones de inventario + users
│   └── seeders/                             # CatalogosSeeder + InventarioDemoSeeder
├── routes/api.php                           # apiResource bienes
├── bootstrap/app.php                        # registro de rutas api
├── doc/                                     # esta documentación
└── md/README.md                             # registro cronológico del desarrollo
```

## Patrón de flujo de una petición

```
Request HTTP
  → routes/api.php
  → BienController (resuelve actor por X-Usuario, temporal)
  → FormRequest (valida payload)
  → BienesService (reglas de negocio, permisos, transacciones)
  → Modelos + Observers (efectos secundarios: bitácora, estado de préstamos)
  → BienResource (respuesta JSON consistente)
```

Decisiones:

1. **La lógica de negocio vive en Services**, no en controllers: permite reutilizar
   los mismos métodos desde commands, jobs u otros módulos, y facilita los tests.
2. **La validación vive en FormRequests**: reglas declarativas, mensajes en español,
   y reglas `unique` dinámicas que excluyen al propio bien en updates.
3. **La serialización vive en Resources**: evita exponer columnas crudas y estabiliza
   el contrato de la API para el frontend PWA.
4. **Los efectos secundarios (triggers SQL) viven en Observers**: la misma lógica
   funciona en SQLite y PostgreSQL sin depender de PL/pgSQL.

## Configuración externa

| Variable (.env) | Uso | Default |
|---|---|---|
| `FRONTEND_URL` | Base para construir la URL completa del QR | `http://localhost:5173` |
| `DB_CONNECTION` | SQLite ahora; cambiar a `pgsql` cuando se conecte Supabase | `sqlite` |

## Estado pendiente

- Autenticación (Sanctum u otra): hoy el actor llega por header `X-Usuario`
  (ver [04-permisos-usuarios.md](04-permisos-usuarios.md)).
- Conexión a Supabase.
- Colección Bruno.
