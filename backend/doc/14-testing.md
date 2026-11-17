# 14 — Testing

## 1. Estado actual

**164 tests / 813 aserciones, todos en verde.**

```bash
cd backend
php artisan test        # o: composer test (config:clear + test)
php artisan test --filter=InformacionTest   # una suite concreta
```

## 2. Entorno de pruebas

- **SQLite en memoria** (`phpunit.xml`: `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`) — sin tocar la BD real (Supabase).
- `RefreshDatabase` en cada suite + **seeders completos** en cada test
  (`$this->seed()`), así que cada test arranca con los mismos datos demo.
- `APP_ENV=testing`, cache array, mail array, queue sync.
- `BCRYPT_ROUNDS=4` para que el login en tests sea rápido.
- `config/cors.php` lee `app.env = testing` → orígenes localhost
  permitidos para `CorsTest`.

## 3. Suites

| Suite | Tests | Cubre |
|---|---|---|
| `AuthTest` | 7 | login válido/inválido, usuario inactivo, endpoints con token, logout, expiración del token |
| `BienesCrudTest` | 17 | alta por admin/profesor/alumno, identificador 7 dígitos y duplicados, listado por rol, edición, papelera |
| `BienesVistasTest` | 20 | resumen del listado, vista papelera, filtros aula/edificio, endpoint aulas, restaurar, purgar |
| `BienesPorCodigoTest` | 8 | escaneo por código de barras, no_serie y URL de QR; 404/403 |
| `BienesFotosTest` | 15 | alta con foto, formatos/peso, proxy de lectura con autorización, reemplazo, purga borra archivo |
| `PrestamosTest` | 21 | solicitud (alumno/profesor/admin), estados, aceptar/devolver/confirmar, reglas de cada acción |
| `PrestamosHistorialTest` | 8 | vistas solicitados/recibidos/general y acceso al detalle |
| `AuditoriasTest` | 16 | crear/listar/ver, registro de escaneos, duplicados, sincronización de estado con bitácora |
| `ReportesTest` | 22 | los 11 reportes, accesos por rol/persona, filtros por adscripción |
| `InformacionTest` | 25 | 17 endpoints de catálogos, cascadas, RBAC de bienes, QR, filtros |
| `CorsTest` | 3 | preflight, cabeceras en respuestas, origen desconocido sin CORS |
| `ExampleTest` | 2 | smoke tests base |

## 4. Convenciones de los tests

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed();
    $this->admin    = Usuario::query()->find(1);  // Administrador, persona 1
    $this->profesor = Usuario::query()->find(3);  // Profesor, persona 3
    $this->alumno   = Usuario::query()->find(4);  // Estudiante, persona 8
}

$this->actingAs($this->profesor, 'sanctum')
    ->getJson('/api/informacion/aulas')
    ->assertStatus(200)
    ->assertJsonCount(0, 'data');
```

- `actingAs($user, 'sanctum')` simula el token de la PWA.
- Los assertions comparan contra consultas directas a la BD (contar
  bienes esperados, etc.) — no se asume el número exacto del seeder.
- Errores esperados: `assertStatus(403/404/422)` según el service.
- Los datos que un test necesita y el seeder no cubre se crean/actualizan
  en el propio test (p. ej. `persona.cve_carrera`, marcar un bien
  'prestado').

## 5. Qué NO cubren los tests

- Conexión real a Supabase ni al Google Drive real (fotos con disco
  `local` simulado).
- Race conditions (locks) — pendiente sugerido en el backlog.
- Rendimiento/paginación con volúmenes grandes.
