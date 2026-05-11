# 📐 Documento Técnico de Arquitectura v2.0

## Intérmica S.A.S - Plataforma Operativa

**Fecha:** 2026-05-11
**Versión:** 2.0
**Estado:** En Desarrollo

---

## 1. Visión General

Arquitectura fullstack robusta para gestión de servicios termográficos industriales con:

- **Frontend:** React (JavaScript) - SPA responsive
- **Backend:** PHP 8.1+ con API RESTful
- **Base de Datos:** MySQL 5.7+ / MariaDB
- **Autenticación:** JWT con refresh tokens
- **Autorización:** RBAC (3 roles: admin, técnico, cliente)

---

## 2. Stack Tecnológico

### Frontend
- React 18.3+
- Redux Toolkit para estado global
- React Router v6 para navegación
- Axios para HTTP
- React Hook Form para formularios
- Tailwind CSS o similar para estilos
- QR Code para códigos dinámicos
- html2pdf para generación de documentos

### Backend
- PHP 8.1+ (strict_types habilitado)
- PDO para conexiones a BD
- Firebase JWT para tokens
- PHPUnit para testing
- Monolog para logging
- TCPDF para generación de PDFs
- PHP-QRCode para códigos QR

### Base de Datos
- MySQL 5.7+ / MariaDB 10.3+
- Normalización 5NF
- Triggers para automatizaciones
- Views para reportes

---

## 3. Patrones Arquitectónicos

### Backend

#### MVC + Services
```
Routes → Controllers → Services → Models → Database
  ↓
Middleware (Auth, Validation, Logging)
  ↓
Exception Handling → Response Helper
```

#### Capas

1. **Routes:** Definición de endpoints
2. **Middleware:** Autenticación, validación, logging
3. **Controllers:** Orquestación de lógica
4. **Services:** Reglas de negocio
5. **Models:** Mapeo a BD
6. **Helpers:** Utilidades (JWT, PDF, QR, etc.)
7. **Validators:** Validación de datos
8. **Exceptions:** Manejo centralizado de errores

### Frontend

#### Component-Driven Architecture
```
Pages (Layouts)
  ↓
Components (Reutilizables)
  ↓
Hooks (Lógica compartida)
  ↓
Store (Redux - Estado global)
  ↓
API Layer (Llamadas a backend)
```

---

## 4. Flujos Críticos

### 4.1 Autenticación (Login)

```
1. Frontend: POST /api/v1/auth/login
   Payload: { email, password }

2. Backend:
   - Validar email existe
   - Validar contraseña con bcrypt
   - Generar JWT (exp: 3600s)
   - Generar Refresh Token (exp: 30 días)
   - Crear sesión en SESIONES_JWT
   - Registrar en AUDITORIA

3. Response:
   {
     access_token: JWT,
     refresh_token: JWT,
     expires_in: 3600,
     user: { id, email, nombre, rol }
   }

4. Frontend:
   - Guardar tokens en localStorage/sessionStorage
   - Redirigir según rol
```

### 4.2 Generación de Cuenta de Cobro (RN-06)

```
1. Técnico completa servicio
   - Estado: pendiente → completado
   - Registra horas, materiales, gastos

2. Backend dispara RN-06:
   - Calcula total con parámetros
   - Genera número: CC-YYYY-XXXX
   - Crea registro en CUENTAS_COBRO
   - Crea items relacionados
   - Genera PDF y QR dinámico

3. Cliente recibe notificación
   - Email con enlace a cuenta
   - Puede visualizar PDF
   - Escanea QR para verificar autenticidad
```

### 4.3 Actualización de Stock (RN-02)

```
1. Técnico descarga materiales para servicio

2. Backend:
   - Valida stock disponible
   - Reduce cantidad en STOCK
   - Crea movimiento en MOVIMIENTOS_STOCK
   - Registra en AUDITORIA

3. Sistema alerta si:
   - Stock < cantidad mínima
   - Producto agotado
```

### 4.4 Bloqueo de Agenda (RN-13/14)

```
1. Técnico solicita bloqueo de fecha

2. Backend valida:
   - Fecha no en el pasado
   - No hay servicios asignados
   - Técnico existe y está activo

3. Crea registro en BLOQUEOS_AGENDA

4. Sistema:
   - Impide asignar servicios en fechas bloqueadas
   - Muestra en calendario como "No disponible"
```

---

## 5. Estructura de Base de Datos

### Tablas Principales

1. **USUARIOS** - Autenticación
   - id, email, password_hash, nombre, rol, activo, fecha_creacion

2. **SESIONES_JWT** - Control de sesiones
   - id, usuario_id, token, refresh_token, ip_address, activa, fecha_inicio, fecha_expiracion

3. **TECNICOS** - Datos técnicos
   - id, usuario_id, especialidad, disponible, activo

4. **CLIENTES** - Datos clientes
   - id, usuario_id, empresa, nit, contacto, activo

5. **SERVICIOS** - Servicios solicitados
   - id, numero_servicio, cliente_id, tecnico_id, estado, fecha_servicio, fecha_completacion

6. **CUENTAS_COBRO** - Facturas generadas
   - id, numero, cliente_id, servicios_ids, total, estado, fecha_pago

7. **PARAMETROS_COTIZADOR** - Configuración de precios
   - id, nombre, valor, activo

8. **STOCK** - Inventario de materiales
   - id, producto, cantidad, cantidad_minima, valor_unitario

9. **AUDITORIA** - Log de cambios
   - id, tabla, registro_id, usuario_id, accion, estado_anterior, estado_nuevo, fecha

---

## 6. Seguridad

### Autenticación
- JWT con firma HMAC-SHA256
- Refresh tokens con rotación automática
- Session timeout: 1 hora (access token)
- Sesiones registradas en BD para revocación

### Autorización
- RBAC con 3 roles: admin, tecnico, cliente
- Middleware RBACMiddleware valida permisos
- Rutas protegidas según rol

### Encriptación
- Contraseñas: bcrypt (cost: 12)
- Tokens: HMAC-SHA256
- PDFs/QR: Base64 en respuestas

### Validación
- Input validation en Validators/
- Output sanitization
- SQL injection prevention via PDO prepared statements
- CSRF protection con tokens

### Logging
- Auditoria de todas las transiciones de estado (RN-16)
- Logs separados: app.log, auditoria.log, errores.log
- Rotación de logs cada 7 días

---

## 7. API Response Standard

```json
{
  "success": true,
  "status": 200,
  "timestamp": "2026-05-11T20:47:46Z",
  "message": "Operación exitosa",
  "data": {
    // Payload específico
  },
  "meta": {
    "version_api": "1.0.0",
    "tiempo_respuesta_ms": 145
  }
}
```

---

## 8. Integridad de Datos

### Normalización: 5NF

Todas las tablas cumplen hasta 5NF:
- 1NF: Atomicidad
- 2NF: Dependencia total
- 3NF: Sin dependencias transitivas
- BCNF: Determinantes son claves candidatas
- 4NF: Dependencias multivaluadas
- 5NF: Dependencias join

### Integridad Referencial

- Foreign keys con `ON DELETE RESTRICT` (RN-25)
- Cascadas solo en relaciones n:n

### Borrado Lógico (RN-23)

- Campo `activo` (1=activo, 0=inactivo)
- Nunca borrar físicamente
- Consultas filtran `WHERE activo = 1` por defecto

---

## 9. DevX (Developer Experience)

### Desarrollo Local

```bash
# Backend
cd backend && composer serve

# Frontend
cd frontend && npm start

# Ambos corriendo:
# Backend: http://localhost:8000
# Frontend: http://localhost:3000
```

### Testing

```bash
# Backend
composer test

# Frontend
npm test
```

### Documentación

- Postman Collection en `/docs/postman/`
- API Docs en `/docs/API_REFERENCE.md`
- Arquitectura en `/TECHNICAL_ARCHITECTURE.md`

---

## 10. Próximas Versiones

- **v2.1:** Notificaciones en tiempo real (WebSockets)
- **v2.2:** Integración con pasarelas de pago
- **v2.3:** App móvil nativa (React Native)
- **v3.0:** Microservicios con Docker

---

**Documento generado:** 2026-05-11
**Responsable:** Arquitectura Fullstack
