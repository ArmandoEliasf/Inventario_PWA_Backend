# 12 — CORS y PWA

## 1. CORS (backend listo para la PWA)

La PWA es un proyecto frontend **aparte** en otro origen; el navegador
bloquea las llamadas cross-origin sin cabeceras CORS. Configuración en
`config/cors.php` (Laravel 12 trae `HandleCors` en el middleware global;
no hay que registrar nada). Tests: `tests/Feature/CorsTest.php`.

| Decisión | Valor | Motivo |
|---|---|---|
| `paths` | `api/*`, `up` | Solo la API JSON y el health check |
| `allowed_origins` | `FRONTEND_URL` + `FRONTEND_URLS` (coma) + `localhost:5173`/`127.0.0.1:5173` solo en local/testing | Orígenes desde `.env`, sin tocar código; varios builds (staging/prod) |
| `allowed_methods` | `*` | La API usa GET/POST/PUT/DELETE |
| `allowed_headers` | `*` | `Authorization` (token) y `Content-Type` (JSON/multipart de fotos) |
| `exposed_headers` | `[]` | Todo viaja en el cuerpo (`{data}`) |
| `supports_credentials` | `false` | Token Bearer, nunca cookies (y `*`+credenciales lo prohíbe el Fetch spec) |
| `max_age` | `3600` | Preflight cacheado 1 h: menos peticiones OPTIONS en móvil |
| `allowed_origins_patterns` | vacío (ejemplo comentado) | Para wildcards de subdominios si algún día se necesita |

**Regla de seguridad**: nunca `*` en producción — con tokens Bearer un
origen abierto deja consumir la API desde cualquier sitio.

### Variables .env

```ini
FRONTEND_URL=https://inventario-dsm.vercel.app   # origen de la PWA
# FRONTEND_URLS=https://staging...,https://prod...   # orígenes extra
```

## 2. Qué es "PWA-ready" en este backend

| Requisito PWA | Estado en el backend |
|---|---|
| API JSON sin estado | ✔ token Bearer Sanctum, sin sesiones |
| CORS para el origen de la PWA | ✔ `config/cors.php` |
| Auth apta para offline (token local) | ✔ token en el front + expiración 480 min |
| Escaneo de códigos | ✔ `GET /bienes/por-codigo/{codigo}` (QR/barras/identificador) |
| Subida de fotos desde el móvil | ✔ multipart ≤ 5 MB + proxy de lectura con `Cache-Control: private, max-age=86400` |
| Idempotencia para colas offline | parcial: duplicados en auditoría → 422 (el front puede identificar y saltar); crear bienes con identificador único → 422 |
| Health check | ✔ `GET /up` |
| HTTPS | requerido en producción (cámara + service worker) |

## 3. Lo que falta es del lado del frontend

La PWA en sí (manifest, service worker, offline) se construye en el
proyecto front. Guía resumida:

```
Frontend (Vite + Vue/React):
├── vite-plugin-pwa          → manifest.json + service worker (Workbox)
├── manifest.json            → instalable (nombre, iconos, theme_color)
├── html5-qrcode             → escáner (componente listo en
│                             ../docs/frontend-escaner-auditorias.md)
├── Estrategia offline:
│   ├── SW precachea el shell de la app
│   ├── GET API → stale-while-revalidate / cache-first con expiración
│   ├── POST/PUT → cola en IndexedDB + retry cuando vuelva la red
│   │   (los 422 de duplicados del backend permiten deduplicar)
│   └── foto del bien → cache-first (el proxy ya envía Cache-Control)
├── Token en localStorage + interceptor 401 → re-login
└── Deep links de QR: {FRONTEND_URL}/bienes/{id} abre la ficha del bien
```

Requisitos de plataforma:
- **HTTPS obligatorio** en producción: `getUserMedia` (cámara) y
  service workers no funcionan en HTTP (salvo `localhost`).
- El rol del login (`rol`) ajusta la interfaz inmediatamente; `GET /me`
  lo confirma (con `matricula` para estudiantes).

## 4. Errores de integración típicos y su causa

| Síntoma en la PWA | Causa probable |
|---|---|
| "CORS policy: No 'Access-Control-Allow-Origin'" | El origen no está en `FRONTEND_URL`/`FRONTEND_URLS` |
| 401 después de 8 h | Token expirado (480 min) → re-login |
| Cámara no abre en producción | Falta HTTPS |
| Foto no carga | 403 (estudiante/ajeno) o 404 (bien sin foto) |
| 429 en login | Más de 10 intentos por minuto |
