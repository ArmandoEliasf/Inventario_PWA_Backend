# 01 — Arquitectura

## 1. Stack

| Capa | Tecnología | Detalle |
|---|---|---|
| Framework | Laravel 12 (PHP ≥ 8.2) | `backend/composer.json` |
| Autenticación | Sanctum 4 | Tokens personales con expiración de 480 min |
| Base de datos | PostgreSQL (Supabase) | Producción; SQLite en memoria para tests |
| Almacenamiento de fotos | Google Drive | Driver custom registrado con `masbug/flysystem-google-drive-ext` |
| Tests | PHPUnit 11 | `RefreshDatabase` + seeders completos |
| Code style | Laravel Pint | `vendor\bin\pint` |

La PWA es un **proyecto frontend aparte** (URL configurada en
`FRONTEND_URL`). Este backend es una API JSON sin vistas ni sesiones.

## 2. Patrón de diseño: Controller → Service → Resource

Aplicado de forma uniforme en todos los módulos:

```
Petición HTTP
   │
   ▼
[Rutas] routes/api.php ── middleware 'auth:sanctum' + throttle en login
   │
   ▼
[Controller] app/Http/Controllers/*
   │  delgado: valida query params ($request->validate), delega,
   │  envuelve la respuesta en {'data': ...} + 'message' en mutaciones
   │
   ├── [Request] app/Http/Requests/*      (payloads de POST/PUT)
   │     reglas de validación declarativas + mensajes en español
   │
   ▼
[Service] app/Services/*
   │  TODAS las reglas de negocio:
   │  - control de acceso por rol (Admin/Profesor/Estudiante)
   │  - reglas de propiedad (cve_encargado)
   │  - transacciones, bitácora, generación de códigos
   │  Los errores de regla se lanzan con abort(403/404/422) o
   │  ValidationException::withMessages([...]) → 422
   │
   ▼
[Resource] app/Http/Resources/*
      serialización JSON: dos niveles por módulo
      (resumen para listados + detalle completo)

   ── transversal ──
[Observers] app/Observers/*      sincronización de estados + bitácora
[Models]    app/Models/*         Eloquent sobre el esquema real (español)
```

**Por qué los services cargan con la autorización**: un mismo service se
consume desde varios endpoints (p. ej. `BienesService::obtener` lo usan
el detalle, la edición, la papelera y la foto); centralizar el control
garantiza que no haya puntos ciegos.

## 3. Flujo de una petición típica

Ejemplo: `PUT /api/bienes/12` con token Bearer.

1. `public/index.php` → `bootstrap/app.php` configura la aplicación.
2. Middleware global de Laravel 12: `HandleCors` (config en
   `config/cors.php`), `TrimStrings`, `ValidatePostSize`, etc.
3. Enrutamiento: el grupo `Route::middleware('auth:sanctum')` exige
   token. Sanctum resuelve el `Usuario` desde `personal_access_tokens`;
   si el token tiene más de 480 min, se rechaza con 401.
4. `BienController::update` recibe `UpdateBienRequest` (Laravel valida el
   payload antes de ejecutar el método; falla con 422 y detalle por campo).
5. El controller llama a `BienesService::editar($actor, 12, $datos)`:
   - `obtener()` verifica acceso (404 si no existe, 403 si el profesor no
     es encargado).
   - Regenera el código de barras si cambió el artículo.
   - `ValidationException` si el identificador ya está usado (422).
   - `$bien->save()` dispara `BienObserver::updated` si cambió el estado
     físico → bitácora "Revisión de Inventario".
6. La respuesta se serializa con `BienResource`:
   `{"message": "Bien actualizado correctamente.", "data": {...}}`.

## 4. Estructura de carpetas

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/            (6) Auth, Aula, Bien, Prestamo,
│   │   │                              Auditoria, Reportes, Informacion
│   │   ├── Requests/               (6) Store/Update Bien, Prestamo,
│   │   │                              Auditoria y Detalle
│   │   └── Resources/              (6) Bien*, Prestamo*, Auditoria*
│   ├── Models/                     (27) uno por tabla
│   ├── Observers/                  BienObserver, PrestamoObserver
│   ├── Providers/                  AppServiceProvider registra el disco
│   │                               'google' (Google Drive)
│   └── Services/                   (7) la lógica de negocio
├── bootstrap/app.php               wiring de Laravel 12 (routing,
│                                   middleware, excepciones)
├── config/                         app, auth, cors, database, sanctum,
│                                   services (FRONTEND_URL, fotos), ...
├── database/
│   ├── migrations/                 9 migraciones que espejan el esquema
│   │                               real de producción
│   ├── seeders/                    CatalogosSeeder + InventarioDemoSeeder
│   └── bd-datos reales.sql         dump de referencia de producción
├── doc/                            ESTA documentación
├── routes/                         api.php (toda la API), web.php (vacío)
└── tests/                          Feature/ 9 suites + CorsTest
```

## 5. Convenciones

| Convención | Ejemplo |
|---|---|
| Identificadores con prefijo `cve_` (esquema real) | `cve_bien`, `cve_aula`, `cve_persona` |
| Tablas y columnas en español | `bienes`, `estado_fisico`, `activo` |
| Envelope de respuesta | `{"data": {...}}` en lecturas; `{"message", "data"}` en mutaciones |
| Paginación | `<Modulo>ResumenResource::collection($paginator)` → formato Laravel (`data`, `links`, `meta`) |
| Parámetros de query validados en el controller | `vista`, `cve_aula`, `cve_edificio`, `per_page` (1–100) |
| Sin comentarios de "qué" sino de "por qué" | p. ej. por qué se marca el bien directamente en `solicitar` |
| Fechas | `now()`; los reportes imprimen `d/m/Y` en PHP para compatibilidad Postgres/SQLite |

## 6. Modelos y tablas principales

27 modelos en `app/Models/`. Los que participan en reglas de negocio:

| Modelo | Tabla | Clave | Relaciones clave |
|---|---|---|---|
| `Usuario` | `usuarios` | `cve_usuario` | `role` (cve_rol), `persona` (cve_persona) |
| `Persona` | `persona` | `cve_persona` | `profesor`, `alumno`, `adscripciones` (pivot `adscripcion_persona`) |
| `Bien` | `bienes` | `cve_bien` | `articulo.familia.tipoBien`, `modelo.marca`, `aula`, `encargado` (persona), `prestamos`, `movimientos`, `auditoriasDetalle` |
| `Prestamo` | `prestamos` | `cve_prestamo` | `bien`, `personaSolicita` — `#[ObservedBy(PrestamoObserver)]` |
| `Auditoria` | `auditorias` | `cve_auditoria` | `auditor` (persona), `detalles` |
| `AuditoriaDetalle` | `auditoria_detalle` | `cve_auditoria_det` | `auditoria`, `bien` |
| `BitacoraMovimiento` | `bitacora_movimientos` | `cve_movimiento` | `bien`, `motivo`, `personaAccion` |

Todos los modelos usan `public $timestamps = false` (el esquema maneja
sus propias fechas); `Usuario` redefine `CREATED_AT`/`UPDATED_AT` a
`fecha_registro`/`fecha_actualizacion`.

Ver [02-base-de-datos.md](02-base-de-datos.md) para el esquema completo.
