# Notas para el frontend: escáner QR / código de barras + auditorías

Backend listo y con tests (`99 passed`). Este documento describe qué instalar
en la PWA y cómo consumir los endpoints nuevos.

## 1. Instalación del escáner (PWA)

```bash
npm install html5-qrcode
```

Soporta QR y códigos de barras (EAN, Code128, etc.) con cámara del celular.
Funciona solo bajo **HTTPS o localhost** (requisito de `getUserMedia`).

### Componente de escáner

```js
import { Html5Qrcode } from 'html5-qrcode';

let escanerActivo = null;

export async function iniciarEscaneo(onCodigo, onError) {
  escanerActivo = new Html5Qrcode('lector'); // <div id="lector"></div>
  await escanerActivo.start(
    { facingMode: 'environment' }, // cámara trasera
    { fps: 10, qrbox: 250 },
    async (texto) => {
      await detenerEscaneo();
      onCodigo(texto);
    },
    () => {}, // frames sin lectura: ignorar
  );
}

export async function detenerEscaneo() {
  if (escanerActivo) {
    await escanerActivo.stop();
    escanerActivo.clear();
    escanerActivo = null;
  }
}
```

## 2. Qué hace el backend con el código escaneado

`GET /api/bienes/por-codigo/{codigo}` (protegido con token Sanctum).

Acepta **cualquiera** de estas tres formas de escaneo:

| Escaneo                                | Ejemplo de string recibido                  |
|----------------------------------------|---------------------------------------------|
| QR (URL completa del frontend)         | `http://localhost:5173/bienes/12`           |
| Código de barras                       | `EQ-01-0021-0000007`                        |
| Identificador (no_serie)               | `0000007`                                   |

Respuesta (misma estructura que `GET /api/bienes/{id}`):

```json
{
  "data": { "cve_bien": 12, "nombre": "...", "estado_fisico": "bueno", "...": "..." },
  "qr_url": "http://localhost:5173/bienes/12"
}
```

Errores: `401` sin token, `403` bien ajeno (profesor) o alumno, `404` código
desconocido.

> El flujo pedido: al escanear, llamar a este endpoint y usar `data` para
> abrir la vista "ver bien a detalle" que ya existe en la PWA.

## 3. Flujo de auditoría (endpoints nuevos)

Todos protegidos con token. El alumno (rol Estudiante) recibe `403`.

### 3.1 Crear auditoría

```
POST /api/auditorias
{ "observaciones_generales": "Auditoría semestral del lab" }  // opcional
```

→ `201` con `data.cve_auditoria`. El usuario autenticado queda como auditor.

### 3.2 Escanear bienes y registrarlos en la auditoría

Por cada bien escaneado:

1. `GET /api/bienes/por-codigo/{codigo}` → muestra el detalle en pantalla.
2. Si el usuario confirma:

```
POST /api/auditorias/{cve_auditoria}/detalles
{
  "cve_bien": 12,
  "encontrado": true,
  "estado_encontrado": "regular",      // opcional: bueno | regular | deteriorado
  "observaciones_evidencia": "Texto"   // opcional, máx 500
}
```

→ `201`. Si `estado_encontrado` difiere del estado del bien, el backend
actualiza `bienes.estado_fisico` y deja bitácora "Revisión de Inventario".
Er: `422` si el bien ya está registrado en esa auditoría.

### 3.3 Consultar avance

```
GET /api/auditorias               → listado (resumen, paginado)
GET /api/auditorias/{cve_auditoria} → detalle con todos los bienes escaneados
```

El detalle incluye por cada elemento `data.detalles[]`:

```json
{
  "cve_auditoria_det": 1,
  "encontrado": true,
  "estado_encontrado": "regular",
  "observaciones_evidencia": "...",
  "bien": { "cve_bien": 12, "identificador": "0000007", "nombre": "...", "estado_fisico": "regular", "activo": true },
  "coincide_estado": true
}
```

`coincide_estado` sirve para pintar en rojo los bienes cuyo estado difiere.

### 3.4 Corregir / retirar un escaneo

```
PUT    /api/auditorias/{cve_auditoria}/detalles/{cve_auditoria_det}
DELETE /api/auditorias/{cve_auditoria}/detalles/{cve_auditoria_det}
```

### 3.5 Reglas de acceso

- Profesor: crea y ve solo sus auditorías; solo puede registrar bienes
  donde es encargado.
- Administrador: ve todas las auditorías y registra bienes de cualquiera.
- Director u otros roles: pueden crear auditorías propias pero no ver ajenas.
- Alumno: todo bloqueado (403).

## 4. Ejemplo de integración (pseudocódigo Vue/React)

```js
async function alEscanear(codigoEscaneado) {
  const { data } = await api.get(`/bienes/por-codigo/${encodeURIComponent(codigoEscaneado)}`);
  abrirVistaDetalle(data.data); // componente "ver bien a detalle"

  const ok = await confirmarRegistro(data.data);
  if (!ok) return;

  await api.post(`/auditorias/${auditoriaActiva.cve_auditoria}/detalles`, {
    cve_bien: data.data.cve_bien,
    encontrado: true,
    estado_encontrado: data.data.estado_fisico, // o el que el usuario indique
  });
}
```
