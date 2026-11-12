# Índice de documentación — Backend Inventario PWA

Documentación técnica del sistema. Para el registro cronológico de decisiones y
trabajo realizado, ver `md/README.md`.

| # | Documento | Contenido |
|---|---|---|
| 01 | [Arquitectura](01-arquitectura.md) | Stack, estructura de carpetas, patrones de diseño y flujo de una petición |
| 02 | [Base de datos](02-base-de-datos.md) | Esquema completo: 24 tablas, relaciones, observers que reemplazan triggers |
| 03 | [Reglas de negocio — Bienes](03-negocio-bienes.md) | Código de barras, QR, identificador/no_serie, papelera, estados |
| 04 | [Permisos y usuarios](04-permisos-usuarios.md) | Roles, matriz de permisos, resolución temporal del actor |
| 05 | [API](05-api.md) | Endpoints, payloads de ejemplo y códigos de respuesta |
| 06 | [Datos y seeders](06-datos-y-seeders.md) | Extracción del SQL origen, seeders y datos demo |
| 07 | [Testing](07-testing.md) | Suite de pruebas, cómo correrla y qué cubre |

## Comandos esenciales

```bash
php artisan serve                     # servidor (http://localhost:8000)
php artisan migrate:fresh --seed      # reconstruir BD con datos demo
php artisan test                      # correr la suite completa
vendor\bin\pint                       # formatear código
```

## Credenciales de prueba (datos demo)

Todos los usuarios demo tienen la contraseña `password` (solo válido si se
habilita auth; hoy el acceso es por header `X-Usuario`).

| Usuario | cve_usuario | Rol |
|---|---|---|
| admin | 1 | Administrador |
| director | 2 | Director |
| profesor | 3 | Profesor |
| alumno | 4 | Estudiante |
