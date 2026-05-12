# 📚 API Reference - Intérmica S.A.S

## Base URL
```
http://localhost/intermica-api/api/v1
```

## Autenticación
Todas las peticiones protegidas requieren JWT:

```
Authorization: Bearer {access_token}
```

Las respuestas JSON siguen el formato de `ResponseHelper`: `success`, `status`, `message`, `data` (y opcionalmente `pagination` o `errors`).

---

## 🔐 AUTH (`/api/v1`)

Rutas públicas (sin JWT): `GET /api/v1/health`, `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`.

### POST /api/v1/auth/login

**Request:**
```json
{
  "email": "usuario@example.com",
  "password": "contraseña_segura"
}
```

**Response (200):** `data` contiene directamente los tokens.

```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbGc...",
    "refresh_token": "eyJhbGc...",
    "expires_in": 3600,
    "token_type": "Bearer"
  }
}
```

### POST /api/v1/auth/refresh

**Request:**
```json
{
  "refresh_token": "eyJhbGc..."
}
```

Misma forma de `data` que en el login. El servidor rota la sesión en `sesiones_jwt`.

---

## 👥 USUARIOS (`/api/v1/usuarios`)

| Método | Ruta | Roles | Descripción |
|--------|------|-------|-------------|
| GET | `/api/v1/usuarios/me` | admin, técnico, cliente | Perfil del usuario autenticado (sin `password_hash`) |
| GET | `/api/v1/usuarios` | **admin** | Lista paginada |
| GET | `/api/v1/usuarios/{id}` | admin (cualquiera), técnico/cliente (**solo su id**) | Detalle |
| POST | `/api/v1/usuarios` | **admin** | Alta de usuario |
| PATCH | `/api/v1/usuarios/{id}` | admin (campos amplios) o el propio usuario (perfil limitado) | Actualización |
| DELETE | `/api/v1/usuarios/{id}` | **admin** | Borrado lógico (`activo = 0`; no sobre sí mismo) |

**Query (GET lista):** `page`, `per_page`, `rol` (`admin` \| `tecnico` \| `cliente`), `activo` (0 o 1).

**Response lista:** usa `pagination` estándar (`page`, `per_page`, `total`, `total_pages`); el arreglo de filas va en `data`.

**POST crear (admin):**

```json
{
  "email": "nuevo@example.com",
  "nombre_completo": "Nuevo Usuario",
  "password": "mínimo_8_caracteres",
  "rol": "tecnico",
  "telefono": "",
  "direccion": ""
}
```

(`nombre` se acepta como alias de `nombre_completo`.)

**PATCH:** el **admin** puede cambiar `email`, `nombre_completo`, `rol`, `telefono`, `direccion`, `activo`, `password`. Un **no admin** solo `nombre_completo`, `telefono`, `direccion`, `password` sobre su propio `id`.

---

## 🔧 TÉCNICOS ENDPOINTS (planeado)

Estos endpoints aún no están expuestos en `backend/routes/api.php`.

### GET /tecnicos
Listar técnicos con disponibilidad.

**Query Params:**
- `disponible` (boolean): Solo técnicos disponibles
- `especialidad` (string): Filtrar por especialidad

### GET /tecnicos/{id}/agenda
Obtener agenda del técnico.

**Query Params:**
- `fecha_inicio` (date): YYYY-MM-DD
- `fecha_fin` (date): YYYY-MM-DD

### POST /tecnicos/{id}/bloqueos
Crear bloqueo de agenda (RN-13/14).

**Request:**
```json
{
  "fecha_inicio": "2026-05-15",
  "fecha_fin": "2026-05-20",
  "razon": "Vacaciones",
  "tipo": "vacaciones"
}
```

---

## 📋 SERVICIOS (`/api/v1/servicios`)

Implementado en el router PHP.

| Método | Ruta | Roles | Notas |
|--------|------|-------|--------|
| GET | `/api/v1/servicios` | admin, técnico, cliente | El rol **cliente** solo ve servicios de su empresa (`clientes.usuario_id`) |
| GET | `/api/v1/servicios/{id}` | admin, técnico, cliente | Misma regla de alcance para cliente |
| PATCH | `/api/v1/servicios/{id}/estado` | admin, técnico | Body: `{ "estado": "completado" }`. Si pasa a **completado**, se ejecutan **RN-02** (stock) y **RN-06** (cuenta de cobro) en transacción cuando aplique |

Los estados válidos en base de datos son: `pendiente`, `programado`, `en_proceso`, `completado`, `cancelado`.

### POST /servicios (planeado)

Alta de servicio con ítems: aún no expuesto en el router mínimo; usar migraciones y lógica de dominio al ampliar el API.

---

## 💰 CUENTAS DE COBRO (`/api/v1/cuentas`)

### POST /api/v1/cuentas

**Rol:** admin.

Genera una cuenta de cobro (RN-06) para uno o más servicios **ya completados** del **mismo cliente**. El número `CC-YYYY-XXXX` lo asigna el trigger vía `secuencias_documento`.

**Request:**
```json
{
  "servicios_ids": [42, 43],
  "fecha_vencimiento": "2026-06-10"
}
```

`fecha_vencimiento` es opcional (por defecto +30 días en servidor).

**Response (201):** `data` incluye `id`, `numero`, `total`.

### Rutas adicionales

- `GET /api/v1/cuentas/{id}`: detalle de cuenta.
- `PATCH /api/v1/cuentas/{id}/pagar`: registra pago.
- `GET /api/v1/cuentas/{id}/pdf`: ruta creada, PDF pendiente de implementación (`501`).
- `GET /api/v1/cuentas`: listado planeado.

---

## 📊 REPORTES ENDPOINTS (planeado)

Estos endpoints aún no están expuestos en `backend/routes/api.php`.

### GET /reportes/auditoria
Obtener logs de auditoría (RN-16).

**Query Params:**
- `usuario_id` (int): Filtrar por usuario
- `accion` (string): crear, actualizar, eliminar
- `fecha_inicio` (date): Rango de fechas
- `fecha_fin` (date): Rango de fechas

### GET /reportes/servicios
Reporte de servicios completados.

### GET /reportes/ingresos
Reporte de ingresos por período.

---

## ⚙️ COTIZADOR (`/api/v1/cotizador`)

Catálogo en tablas `parametros_cotizador` y `parametros_equipos`. La simulación aplica parámetros **activos** cuyo `tipo_parametro` contiene `porcentaje` / `percent` (sobre el subtotal de equipos) o `fijo` / `valor_fijo` (suma), luego IVA 19 %.

| Método | Ruta | Roles | Descripción |
|--------|------|-------|-------------|
| GET | `/api/v1/cotizador/parametros` | admin, técnico, cliente | Lista parámetros con `activo = 1` |
| GET | `/api/v1/cotizador/equipos` | admin, técnico, cliente | Lista equipos con `activo = 1` |
| POST | `/api/v1/cotizador/cotizar` | admin, técnico, cliente | Simulación de totales |
| POST | `/api/v1/cotizador/parametros` | **admin** | Planeado: crear parámetro |
| POST | `/api/v1/cotizador/equipos` | **admin** | Planeado: crear equipo |
| PATCH | `/api/v1/cotizador/parametros/{id}` | **admin** | Actualizar parámetro |
| PATCH | `/api/v1/cotizador/equipos/{id}` | **admin** | Actualizar equipo |

### POST /api/v1/cotizador/cotizar

**Request:**
```json
{
  "equipos": [
    { "equipo_id": 1, "cantidad": 2 }
  ]
}
```

**Response:** `subtotal_equipos`, `ajustes_parametros`, `subtotal_con_ajustes`, `iva_19`, `total`, `lineas`.

### POST /api/v1/cotizador/parametros (admin)

**Request (campos principales):**
```json
{
  "codigo": "RECARGO_URGENCIA",
  "nombre": "Recargo urgencia",
  "descripcion": "Opcional",
  "tipo_parametro": "porcentaje",
  "valor_base": 10,
  "unidad": "%",
  "activo": 1
}
```

### POST /api/v1/cotizador/equipos (admin)

**Request:**
```json
{
  "nombre_equipo": "Caldera industrial",
  "tipo_equipo": "caldera",
  "valor_inspeccion_base": 250000,
  "tiempo_inspeccion_minutos": 120,
  "complejidad": "media",
  "activo": 1
}
```

---

## ❌ Códigos de Error

| Código | Descripción |
|--------|-------------|
| 200 | OK |
| 201 | Creado |
| 400 | Solicitud inválida |
| 401 | No autenticado |
| 403 | No autorizado |
| 404 | No encontrado |
| 409 | Conflicto (RN-25: Integridad) |
| 422 | Validación fallida |
| 500 | Error interno |

## 🔄 Códigos de Estado de Servicio (BD)

- `pendiente`: En espera
- `programado`: Fecha u horario definidos
- `en_proceso`: En ejecución
- `completado`: Finalizado (dispara reglas RN-02 / RN-06 cuando corresponde)
- `cancelado`: Cancelado

## 💳 Códigos de Estado de Cuenta (BD)

- `pendiente`, `parcial`, `pagada`, `vencida`, `cancelada` (ver migración `cuentas_cobro`)
