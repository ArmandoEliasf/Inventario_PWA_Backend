# 05 — Módulo Fotos (FotoService)

Fotos de los bienes. Almacenamiento configurable: disco `local`
(dev/tests, en `storage/app/private`) o disco `google` (Google Drive,
producción) registrado en `AppServiceProvider` con el paquete
`masbug/flysystem-google-drive-ext`.

## 1. Archivos

| Archivo | Papel |
|---|---|
| `app/Services/FotoService.php` | Subir / eliminar / servir la foto |
| `app/Http/Controllers/BienController.php` | `subirFoto` y `foto` (proxy) |
| `config/services.php` | `fotos.disk` (`PHOTOS_DISK`) y `fotos.carpeta` (`PHOTOS_FOLDER`) |
| `config/filesystems.php` | disco `google` con credenciales `GOOGLE_DRIVE_*` |
| `tests/Feature/BienesFotosTest.php` | 15 tests |

## 2. Cómo fluye una foto

```
POST /api/bienes (campo 'foto', multipart)        alta directa
POST /api/bienes/{bien}/foto (multipart)          subir/reemplazar
        │  validación: image, jpg/jpeg/png/webp, ≤ 5 MB
        ▼
FotoService::subir(Bien, UploadedFile)
        │  ruta única: bienes/foto-{cve_bien}-{YmdHisu}.{ext}
        │  borra la anterior → writeStream → update bienes
        │    foto_drive_id = ruta      foto_url = URL pública de Drive (o null)
        ▼
La PWA NO consume la URL de Drive directamente:
GET /api/bienes/{bien}/foto  (endpoint proxy)
        │  BienController::foto → BienesService::obtener (403 propiedad)
        ▼
FotoService::respuesta(Bien) → StreamedResponse con el archivo
        Content-Type del disco, 'inline', Cache-Control: private, max-age=86400
```

`BienResource.foto_url` siempre apunta al **proxy** (con autorización);
`tiene_foto` (boolean) está disponible para la UI. `foto_url` de la
columna de BD (enlace público de Drive) se usa internamente, no se
expone al front.

## 3. Funciones, una por una

### Constantes
- `EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp']` — mismas que la
  validación del request.
- `MAX_KILOBYTES = 5120` (5 MB).

### `subir(Bien $bien, UploadedFile $foto): Bien`
1. Resuelve el disco (`config('services.fotos.disk')`) y la carpeta
   (`services.fotos.carpeta`, default `bienes`).
2. Construye la ruta: `foto-{cve_bien}-{timestamp-micro}.{ext}`. El
   sufijo temporal garantiza rutas únicas por versión: en Drive un
   reemplazo con el mismo nombre sería problemático.
3. **Borra la foto anterior** del disco (antes de escribir la nueva).
4. Escribe por `writeStream` (memoria eficiente; nunca carga el archivo
   completo en RAM) y cierra el stream en `finally`.
5. Actualiza `foto_drive_id` y `foto_url` (`urlPublica`), guarda y
   devuelve el bien.

### `eliminar(Bien): Bien`
Borra el archivo y limpia ambas columnas (`foto_drive_id`, `foto_url`).

### `borrarArchivo(?string $ruta): bool`
Borra solo el archivo en el disco. Lo usa `BienesService::purgar`
(cuando la fila del bien ya va a desaparecer) y `subir` (reemplazo).
Retorna false si la ruta es null, no existe o el disco falla — nunca
lanza, para no interrumpir transacciones.

### `respuesta(Bien): StreamedResponse`
Endpoint proxy de lectura:
- 404 si el bien no tiene `foto_drive_id` o el archivo no existe.
- `readStream` + `fpassthru` dentro de un `StreamedResponse`.
- Cabeceras: `Content-Type` real del disco,
  `Content-Disposition: inline; filename=...`,
  `Cache-Control: private, max-age=86400` (un día de caché local —
  ayuda a la PWA a no re-bajar la foto en móvil).

### `disco()` / `carpeta()` / `urlPublica()` (privados)
- `urlPublica` solo devuelve URL si el adapter es `GoogleDriveAdapter`
  (`getUrl`); con disco local devuelve `null` (sin URL pública, la PWA
  usa el proxy). Nunca lanza: envuelve en try/catch.

## 4. Reglas de acceso (viven en BienesService/Controller)

| Acción | Quién |
|---|---|
| Subir con el bien en alta (`POST /api/bienes` + `foto`) | admin o profesor (quien crea queda encargado) |
| Subir/reemplazar (`POST /api/bienes/{bien}/foto`) | encargado del bien o admin (via `obtener`) |
| Ver (`GET /api/bienes/{bien}/foto`) | encargado o admin — Estudiante 403 |
| Borrar la foto | solo por purga del bien (no hay endpoint de borrado suelto) |

## 5. Errores

| Situación | Respuesta |
|---|---|
| Formato no permitido / demasiado pesado | 422 (validación del request) |
| Bien ajeno (profesor) o estudiante | 403 |
| Bien sin foto / archivo perdido | 404 "El bien no tiene foto." |
| Bien inexistente | 404 (`obtener`) |
| Fallo al subir en la creación | rollback: el bien se elimina y se relanza la excepción |

## 6. Configuración (.env)

```ini
PHOTOS_DISK=local            # local (dev) | google (producción)
PHOTOS_FOLDER=bienes         # carpeta dentro del disco
GOOGLE_DRIVE_CLIENT_ID=      # solo con PHOTOS_DISK=google
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER=
```
