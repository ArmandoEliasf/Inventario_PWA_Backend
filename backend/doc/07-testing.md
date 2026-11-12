# 07 — Testing

## Comandos

```bash
php artisan test                       # suite completa
php artisan test --filter=BienesCrudTest   # solo CRUD de bienes
vendor\bin\pint                        # formatear estilo
```

Configuración: `phpunit.xml` usa BD separada de desarrollo (por defecto
`:memory:`/archivo de tests según env del proyecto Laravel 12) y
`RefreshDatabase` re-crea el esquema + seed en cada test.

## Suite: `tests/Feature/AuthTest.php`

7 tests: login correcto (estructura `token` + `usuario.rol`), contraseña
incorrecta (422), usuario inexistente (422), usuario inactivo (422), consumo
de endpoint protegido con Bearer token, revocación en logout (el token
desaparece de `personal_access_tokens`) y **expiración de token**: al atrasar
`created_at` del token más allá de `SANCTUM_TOKEN_EXPIRATION` (480 min),
el endpoint protegido responde 401.

## Suite: `tests/Feature/BienesCrudTest.php`

19 tests, 65 aserciones. Datos: se siembra la BD demo completa en cada test
(`$this->seed()`), y se usa el rol del usuario demo:

| Constante | cve_usuario | Rol simulado |
|---|---|---|
| `$this->admin` | 1 | Administrador |
| `$this->profesor` | 3 | Profesor (persona 3) |
| `$this->alumno` | 4 | Estudiante |

## Suite: `tests/Feature/BienesVistasTest.php`

19 tests: vistas del listado (resumen de campos, papelera, filtro por aula,
filtro por edificio admin, endpoint `/api/aulas`, bloqueo de alumno, detalle
con foto + código de barras) y acciones de papelera: restaurar (admin/profesor,
bien no en papelera → 422, bien ajeno → 403, alumno → 403) y purgar
(borrado físico con dependencias, rastro en bitácora, bien activo → 422,
bien ajeno → 403).

## Qué cubre

### Creación
- Admin crea bien: formato del código de barras
  `^[A-Z0-9]{1,2}-[A-Z0-9]{1,2}-[A-Z0-9]{1,4}-[0-9]{7}$`, QR `bienes/{id}`,
  `no_serie` = identificador, estado inicial `disponible`/`activo`.
- Profesor crea bien y queda como encargado (persona 3).
- Alumno no puede crear (403).
- Identificador: obligatorio de 7 dígitos; los ceros iniciales se conservan
  como string; duplicado en cualquier artículo → 422 (unicidad global por `no_serie`).

### Consulta
- Alumno bloqueado en index y show (403).
- Profesor ve únicamente sus bienes (se verifica cada item).
- Admin ve todos (per_page por defecto = 20).
- Profesor no puede ver un bien ajeno (403).

### Edición
- Profesor edita su bien: nombre, estado físico, y al cambiar el artículo el
  prefijo TIPO-FAMILIA-ARTICULO del código de barras se re-genera conservando
  el identificador (verificado segmento por segmento).
- Profesor no edita bien ajeno (403); admin edita cualquiera (200).

### Papelera
- Profesor envía su bien disponible a papelera: `activo=false` + un nuevo
  registro en `bitacora_movimientos` (motivo Baja Definitiva).
- Bien prestado no se puede papelerizar (422).
- Profesor no papeleriza bien ajeno (403).

### Autenticación temporal
- Sin header `X-Usuario` → 401.

## Escritura de nuevos tests

Convenciones:
1. `use RefreshDatabase;` + `$this->seed()` en `setUp()`.
2. Peticiones por `getJson/postJson/putJson/deleteJson` con `actingAs(..., 'sanctum')`.
3. Asserts de negocio sobre el modelo fresco (`$bien->refresh()`), no solo sobre
   la respuesta JSON.
