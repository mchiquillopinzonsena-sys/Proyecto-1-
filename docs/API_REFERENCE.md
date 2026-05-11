# 📚 API Reference - Intérmica S.A.S

## Base URL
```
http://localhost/intermica-api/api/v1
```

## Autenticación
Todas las peticiones (excepto Login) requieren JWT en header:
```
Authorization: Bearer {access_token}
```

---

## 🔐 AUTH ENDPOINTS

### POST /auth/login
Autenticación de usuario.

**Request:**
```json
{
  "email": "usuario@example.com",
  "password": "contraseña_segura"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "usuario": {
      "id": 1,
      "email": "usuario@example.com",
      "nombre": "Juan Pérez",
      "rol": "tecnico"
    },
    "tokens": {
      "access_token": "eyJhbGc...",
      "refresh_token": "eyJhbGc...",
      "expires_in": 3600
    }
  }
}
```

### POST /auth/refresh
Refrescar token expirado.

**Request:**
```json
{
  "refresh_token": "eyJhbGc..."
}
```

### POST /auth/logout
Cerrar sesión.

**Response (200):**
```json
{
  "success": true,
  "message": "Sesión cerrada exitosamente"
}
```

---

## 👥 USUARIOS ENDPOINTS

### GET /usuarios
Listar todos los usuarios (Admin only).

**Query Params:**
- `page` (int): Página de resultados
- `per_page` (int): Items por página
- `rol` (string): Filtrar por rol
- `activo` (boolean): Filtrar activos

**Response (200):**
```json
{
  "success": true,
  "data": {
    "usuarios": [...],
    "pagination": {
      "page": 1,
      "per_page": 30,
      "total": 150
    }
  }
}
```

### POST /usuarios
Crear nuevo usuario (Admin only).

**Request:**
```json
{
  "email": "nuevo@example.com",
  "nombre": "Nuevo Usuario",
  "password": "contraseña_segura",
  "rol": "tecnico"
}
```

### GET /usuarios/{id}
Obtener detalle de usuario.

### PUT /usuarios/{id}
Actualizar usuario.

### DELETE /usuarios/{id}
Desactivar usuario (Borrado lógico).

---

## 🔧 TÉCNICOS ENDPOINTS

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

## 📋 SERVICIOS ENDPOINTS

### GET /servicios
Listar servicios con filtros.

**Query Params:**
- `estado` (string): pendiente, asignado, en_progreso, completado
- `tecnico_id` (int): Filtrar por técnico
- `cliente_id` (int): Filtrar por cliente

### POST /servicios
Crear nuevo servicio.

**Request:**
```json
{
  "cliente_id": 5,
  "descripcion": "Inspección térmica",
  "tipo_servicio": "inspeccion",
  "fecha_programada": "2026-05-15T10:00:00Z",
  "ubicacion": "Carrera 5 #12-34",
  "items": [
    {
      "descripcion": "Análisis termográfico",
      "cantidad": 4,
      "unidad": "horas"
    }
  ]
}
```

### PUT /servicios/{id}/estado
Actualizar estado del servicio (RN-16: Auditoría).

**Request:**
```json
{
  "estado": "completado",
  "notas": "Servicio completado exitosamente"
}
```

---

## 💰 CUENTAS DE COBRO ENDPOINTS

### GET /cuentas
Listar cuentas de cobro.

**Query Params:**
- `estado` (string): pendiente, pagada, vencida
- `cliente_id` (int): Filtrar por cliente

### POST /cuentas
Generar cuenta de cobro (RN-06: Generación automática CC-YYYY-XXXX).

**Request:**
```json
{
  "cliente_id": 5,
  "servicios_ids": [42, 43],
  "fecha_vencimiento": "2026-06-10",
  "notas": "Facturación mensual"
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "numero": "CC-2026-0001",
    "cliente_id": 5,
    "total": 2975000.00,
    "estado": "pendiente"
  }
}
```

### GET /cuentas/{id}
Obtener detalle de cuenta (con QR y PDF).

### GET /cuentas/{id}/pdf
Descargar PDF de cuenta de cobro.

### GET /cuentas/{id}/qr
Obtener QR dinámico de cuenta.

---

## 📊 REPORTES ENDPOINTS

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

## ⚙️ PARÁMETROS ENDPOINTS

### GET /parametros/cotizador
Obtener parámetros del cotizador inteligente.

**Response:**
```json
{
  "success": true,
  "data": {
    "equipos": [
      {
        "id": 1,
        "nombre": "Motores Eléctricos",
        "valor_base": 250000,
        "tiempo_estimado": 240
      }
    ],
    "multiplicadores": {
      "urgencia": 1.5,
      "accesibilidad_alta": 1.3
    }
  }
}
```

### PUT /parametros/cotizador
Actualizar parámetros (Admin only).

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

## 🔄 Códigos de Estado de Servicio

- `pendiente`: En espera de asignación
- `asignado`: Técnico asignado
- `en_progreso`: Técnico trabajando
- `completado`: Servicio finalizado
- `cancelado`: Servicio cancelado

## 💳 Códigos de Estado de Cuenta

- `pendiente`: No pagada
- `pagada`: Pago recibido
- `vencida`: Pasada la fecha de vencimiento
- `anulada`: Cancelada (Borrado lógico)
