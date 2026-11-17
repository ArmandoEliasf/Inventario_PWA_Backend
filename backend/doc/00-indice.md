# Índice de documentación — Backend Inventario PWA

API REST en Laravel 12 + Sanctum + PostgreSQL (Supabase) para la PWA de
inventario de la carrera DSM. Documentación técnica completa, organizada
por capas del sistema.

> Resumen ejecutivo (1 página): `../docs/BACKEND-DOCUMENTACION.md`
> Contratos para el frontend PWA: `../docs/frontend-escaner-auditorias.md`,
> `../docs/frontend-reportes.md`

## Mapa de documentos

### Nivel 1 — Visión general

| # | Documento | Contenido |
|---|---|---|
| 01 | [Arquitectura](01-arquitectura.md) | Stack, patrón Controller → Service → Resource, flujo de una petición, estructura de carpetas, convenciones |
| 02 | [Base de datos](02-base-de-datos.md) | 24 tablas, columnas, relaciones, modelos Eloquent y sus casts |
| 03 | [Autenticación y roles](03-autenticacion-y-roles.md) | Sanctum (login/logout/me), expiración de tokens, roles y matriz de permisos global |

### Nivel 2 — Módulos de negocio (código documentado función por función)

| # | Documento | Contenido |
|---|---|---|
| 04 | [Módulo Bienes](04-modulo-bienes.md) | CRUD, identificador 7 dígitos, papelera, código de barras (`CodigoBarrasService`), escaneo por código |
| 05 | [Módulo Fotos](05-modulo-fotos.md) | `FotoService`, discos local/Google Drive, endpoint proxy con autorización |
| 06 | [Módulo Préstamos](06-modulo-prestamos.md) | Flujo de estados, vistas del historial, aceptación/devolución |
| 07 | [Módulo Auditorías](07-modulo-auditorias.md) | Escaneo en campo, detalles, sincronización de estado físico |
| 08 | [Módulo Reportes](08-modulo-reportes.md) | 11 reportes para formatos del frontend (resguardos, bajas, trazabilidad, estadísticas) |
| 09 | [Módulo Información](09-modulo-informacion.md) | Catálogos y datos auxiliares para rellenar formularios automáticamente |
| 10 | [Observers y bitácora](10-observadores-y-bitacora.md) | `BienObserver`, `PrestamoObserver`, `bitacora_movimientos`, motivos |

### Nivel 3 — Infraestructura, referencia y operación

| # | Documento | Contenido |
|---|---|---|
| 11 | [Referencia API](11-api-reference.md) | Los 45+ endpoints: método, auth, parámetros, payloads, respuestas y errores |
| 12 | [CORS y PWA](12-cors-y-pwa.md) | Diseño de CORS para la PWA, requisitos HTTPS, estrategia offline |
| 13 | [Datos y seeders](13-datos-y-seeders.md) | `CatalogosSeeder`, `InventarioDemoSeeder`, usuarios demo, dump de referencia |
| 14 | [Testing](14-testing.md) | 164 tests / 813 aserciones, convenciones, qué cubre cada suite |
| 15 | [Operación y despliegue](15-operacion-y-despliegue.md) | Variables de `.env`, comandos, despliegue (Supabase, Drive), seguridad |

## Cómo leer la documentación

- Cada módulo se documenta con la misma estructura: **archivos → reglas de
  negocio → funciones (una por una) → validaciones → serialización →
  endpoints → errores**.
- Los nombres de código (`cve_bien`, `activo`, etc.) son los de la base de
  datos real; la BD está en español a propósito.
- El actor (`Usuario` autenticado) siempre llega a los services; toda
  regla de rol/propiedad vive en los services, nunca en los controllers.

## Credenciales de prueba (datos demo)

| Usuario | cve_usuario | Rol | Persona |
|---|---|---|---|
| admin | 1 | Administrador | 1 (Juan Pérez) |
| director | 2 | Director | 2 (María López) |
| profesor | 3 | Profesor | 3 (Carlos Sánchez) |
| alumno | 4 | Estudiante | 8 (Sofía Hernández) |

Login: `POST /api/login` con `{"usuario": "admin", "contrasena": "password"}`.

## Comandos esenciales

```bash
php artisan serve                     # servidor (http://localhost:8000)
php artisan migrate:fresh --seed      # reconstruir BD con datos demo
php artisan test                      # suite completa (SQLite en memoria)
vendor\bin\pint                       # formatear código
composer test                         # config:clear + test
```
