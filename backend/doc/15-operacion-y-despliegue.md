# 15 — Operación y despliegue

## 1. Variables de entorno (.env)

Referencia completa (plantilla: `.env.example`):

### Aplicación
| Variable | Descripción |
|---|---|
| `APP_ENV` | local / staging / production |
| `APP_KEY` | clave de cifrado (`php artisan key:generate`) |
| `APP_URL` | URL pública de la API |
| `APP_DEBUG` | false en producción |

### Base de datos (Supabase)
```ini
DB_CONNECTION=pgsql
DB_HOST=<host supabase>
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=<usuario>
DB_PASSWORD=<contraseña>
DB_SSLMODE=require
```

### Sanctum
| Variable | Descripción |
|---|---|
| `SANCTUM_TOKEN_EXPIRATION` | expiración del token en minutos (480 = 8 h) |

### PWA y CORS
| Variable | Descripción |
|---|---|
| `FRONTEND_URL` | URL de la PWA: deep links de QR **y** origen permitido en CORS |
| `FRONTEND_URLS` | (opcional) orígenes extra en CORS, separados por coma |

### Fotos
| Variable | Descripción |
|---|---|
| `PHOTOS_DISK` | `local` (dev) o `google` (producción) |
| `PHOTOS_FOLDER` | carpeta dentro del disco (default `bienes`) |
| `GOOGLE_DRIVE_CLIENT_ID/SECRET/REFRESH_TOKEN/FOLDER` | credenciales Drive (solo con `PHOTOS_DISK=google`) |

Obtención del Refresh Token: Google Cloud Console → Client ID/Secret →
flujo OAuth con scope `drive` (README del paquete
`masbug/flysystem-google-drive-ext`).

### Resto
`SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_*` — defaults de
Laravel; la API no usa sesiones ni correo hoy.

## 2. Comandos

```bash
# Instalación
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --force        # producción (pregunta confirmación)
npm install                        # solo para assets de Laravel (vite)

# Desarrollo
composer dev                       # serve + queue:listen + pail + vite (concurrently)
php artisan serve                  # solo API en http://localhost:8000

# Base de datos
php artisan migrate:fresh --seed   # reconstruye BD con datos demo (¡borra todo!)

# Calidad
php artisan test                   # suite completa (SQLite en memoria)
vendor\bin\pint                    # formateo de código
```

## 3. Despliegue

1. **Servidor PHP ≥ 8.2** con `composer install --no-dev` y
   `php artisan config:cache route:cache`.
2. **HTTPS obligatorio** — requisitos PWA: cámara (`getUserMedia`) y
   service workers del front.
3. **Postgres/Supabase**: credenciales en `.env`; `DB_SSLMODE=require`.
4. **Fotos**: `PHOTOS_DISK=google` con credenciales Drive; en disco
   `local` los archivos viven en `storage/app/private` (montar storage
   persistente si aplica).
5. **CORS**: `FRONTEND_URL` (y `FRONTEND_URLS`) con los orígenes reales
   de la PWA. Nunca `*`.
6. **Health check**: `GET /up` para el monitor del hosting.
7. Migraciones inicial: `php artisan migrate --force` (los seeders solo
   en entornos demo; los datos reales viven en la BD).

## 4. Seguridad

- `.env` **no está en git** (`.gitignore`); contiene credenciales reales
  de Supabase — si se compartió alguna vez, rotarlas.
- Contraseñas bcrypt; login con throttle 10/min.
- Tokens Sanctum expiran (480 min) y se revocan en logout.
- Autorización por rol/propiedad centralizada en los services.
- `APP_DEBUG=false` en producción (los 500 no filtran stack).

## 5. Backlog conocido (documentado en `../docs/BACKEND-DOCUMENTACION.md` §10)

- Race conditions: `lockForUpdate` en `PrestamosService::solicitar` y en
  la unicidad del identificador de bienes.
- Índice único `(cve_auditoria, cve_bien)` en `auditoria_detalle`.
- Motivo semánticamente correcto para "enviar a papelera".
- `BienObserver` que atribuya la bitácora al actor real.
- Endpoint de progreso de auditoría y `PUT /api/auditorias/{id}`.
- Push notifications (VAPID) si la PWA las va a usar.
