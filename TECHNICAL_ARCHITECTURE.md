# 📐 Documento Técnico de Arquitectura v2.0

## Intérmica S.A.S - Plataforma Operativa Fullstack

**Fecha**: 2026-05-11
**Versión**: 2.0
**Estado**: Activa

---

## 1. Visión General de la Arquitectura

### 1.1 Arquitectura de Capas

```
┌─────────────────────────────────────────────────────────┐
│         Capa de Presentación (Frontend)                 │
│  React 18 | Redux | React Router | Axios               │
└─────────────────────────────────────────────────────────┘
                          ↓↑
          ┌───────────────────────────────┐
          │   API REST JSON / HTTP(S)     │
          │  JWT | CORS | Rate Limiting   │
          └───────────────────────────────┘
                          ↓↑
┌─────────────────────────────────────────────────────────┐
│      Capa de Lógica de Negocio (Backend)                │
│  PHP 8.1+ | Services | Validators | Middleware         │
└─────────────────────────────────────────────────────────┘
                          ↓↑
┌─────────────────────────────────────────────────────────┐
│     Capa de Persistencia (Base de Datos)                │
│  MySQL 5.7+ | Stored Procedures | Triggers | Views     │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Componentes Clave

| Componente | Tecnología | Responsabilidad |
|-----------|-----------|------------------|
| Frontend | React 18 | UI, Interacción usuario, Consumo API |
| Backend | PHP 8.1+ | Lógica negocio, Seguridad, Datos |
| BD | MySQL 5.7+ | Persistencia, Integridad, Reporting |
| Cache | Redis (opcional) | Sesiones, Caché de parámetros |
| Logs | Monolog | Auditoría, Trazabilidad |

---

## 2. Arquitectura del Backend

### 2.1 Patrones de Diseño

- **MVC + Services**: Controllers manejan requests, Services contienen lógica
- **Dependency Injection**: Inyección de dependencias en construcción
- **Repository Pattern**: Acceso a datos centralizado
- **Factory Pattern**: Creación de objetos complejos

### 2.2 Flujo de Request

```
Request HTTP
    ↓
Router (routes/api.php)
    ↓
Middleware Stack:
  - CORS Middleware
  - Auth Middleware (JWT validation)
  - RBAC Middleware (role check)
  - Logging Middleware
    ↓
Controller
  - Validate input (Validator)
  - Call Service
  - Handle response
    ↓
Service
  - Business logic
  - Database calls via Models
  - Exception handling
    ↓
Model
  - Database queries
  - Data transformation
    ↓
Response JSON
```

### 2.3 Seguridad

#### JWT (JSON Web Tokens)
- **Algoritmo**: HS256
- **Duración Access Token**: 1 hora (3600 seg)
- **Duración Refresh Token**: 7 días (604800 seg)
- **Payload**:
  ```json
  {
    "sub": "user_id",
    "email": "user@example.com",
    "rol": "admin|tecnico|cliente",
    "iat": 1234567890,
    "exp": 1234571490
  }
  ```

#### Bcrypt Hashing
- **Cost**: 12 (rounds)
- **Algoritmo**: Bcrypt (CRYPT_BLOWFISH)
- **Uso**: Hash de contraseñas en tabla USUARIOS

#### RBAC (Role Based Access Control)
- **Roles**: admin, tecnico, cliente
- **Middleware**: RBACMiddleware valida permisos
- **Scope**: Por controlador/acción

---

## 3. Reglas de Negocio (RN) Implementadas

### RN-02: Actualización Automática de Stock
**Descripción**: Al completar un servicio, actualizar automáticamente stock de equipos/materiales

**Implementación**:
- **Service**: `StockService::updateStockOnServiceCompletion()`
- **Trigger BD**: `after_update_servicios` (estado → completado)
- **Tabla**: `MOVIMIENTOS_STOCK` registra cada cambio
- **Auditoría**: Log en `AUDITORIA` con usuario_id y timestamp

### RN-06: Generación Automática de Cuentas de Cobro
**Descripción**: Generar CC con formato CC-YYYY-XXXX automáticamente

**Implementación**:
- **Service**: `CuentaCobroService::generarNumeroCuenta()`
- **Lógica**:
  ```php
  $anno = date('Y');
  $ultimoNumero = DB::table('cuentas_cobro')
    ->whereYear('fecha_creacion', $anno)
    ->max('numero_secuencial') ?? 0;
  $numero = sprintf('CC-%04d-%04d', $anno, $ultimoNumero + 1);
  ```
- **Validación**: Unicidad por año

### RN-13/14: Validación y Bloqueo de Agenda Técnico
**Descripción**: Validar disponibilidad técnico, bloquear slots ocupados

**Implementación**:
- **Service**: `AgendaService::validarDisponibilidad()`
- **Tabla**: `BLOQUEOS_AGENDA` (tecnico_id, fecha_inicio, fecha_fin, razon)
- **Validación**: 
  - Solapamiento de horas
  - Bloqueos por vacaciones/mantenimiento
  - Tiempo mínimo entre servicios (30 min)

### RN-16: Auditoría de Transiciones de Estado
**Descripción**: Registrar cada cambio de estado con usuario, fecha, detalles

**Implementación**:
- **Service**: `AuditoriaService::registrarCambio()`
- **Tabla**: `AUDITORIA`
- **Campos**:
  - entidad_tipo (servicios, cuentas_cobro, etc.)
  - entidad_id
  - usuario_id
  - accion (crear, actualizar, eliminar)
  - estado_anterior
  - estado_nuevo
  - detalles (JSON)
  - fecha_registro (timestamp)

### RN-23: Borrado Lógico
**Descripción**: No eliminar registros físicamente, usar flag `activo`

**Implementación**:
- **Campo**: `activo` (TINYINT(1)) en todas las tablas
- **Valor**: 1 (activo), 0 (inactivo/eliminado)
- **Queries**: WHERE activo = 1 en SELECTs
- **Soft Delete**: UPDATE tabla SET activo = 0 WHERE id = X

### RN-25: Integridad Referencial
**Descripción**: Proteger integridad histórica con ON DELETE RESTRICT

**Implementación**:
```sql
ALTER TABLE cuentas_cobro
ADD CONSTRAINT fk_cliente_cuenta
FOREIGN KEY (cliente_id) REFERENCES clientes(id)
ON DELETE RESTRICT ON UPDATE CASCADE;
```

---

## 4. Arquitectura de Base de Datos

### 4.1 Normalización
- **Forma Normal**: 5NF (Fifth Normal Form)
- **Principios**:
  - 1NF: Atributos atómicos
  - 2NF: Dependencia funcional completa
  - 3NF: Sin dependencias transitivas
  - BCNF: Determinantes son claves candidatas
  - 5NF: Descomposición sin pérdida de información

### 4.2 Tablas Principales

#### USUARIOS
```
id (PK)
email (UNIQUE)
nombre
hash_contrasena (bcrypt)
rol (admin|tecnico|cliente)
activo (0|1)
fecha_creacion
fecha_ultima_login
```

#### SESIONES_JWT
```
id (PK)
usuario_id (FK) → USUARIOS
token_jti (UNIQUE)
token_hash
ip_address
user_agent
fecha_inicio
fecha_expiracion
activa (0|1)
```

#### SERVICIOS
```
id (PK)
numero_servicio (UNIQUE, CC-YYYY-XXXX)
cliente_id (FK) → CLIENTES
tecnico_id (FK) → TECNICOS
fecha_servicio
hora_inicio
hora_fin
lugar_servicio
descripcion
estado (cotizado|confirmado|en_progreso|completado|cancelado)
valor_total
activo (0|1)
fecha_creacion
```

#### CUENTAS_COBRO
```
id (PK)
numero (UNIQUE, CC-YYYY-XXXX)
cliente_id (FK) → CLIENTES
fecha_emision
fecha_vencimiento
fecha_pago
subtotal
impuesto_iva
descuento
total
estado (pendiente|pagada|vencida|anulada)
activo (0|1)
fecha_creacion
```

#### PARAMETROS_COTIZADOR
```
id (PK)
equipo_id (FK) → EQUIPOS
variable_nombre (voltaje, corriente, etc.)
valor_minimo
valor_maximo
costo_unitario
factor_multiplicador
activo (0|1)
fecha_actualizacion
```

### 4.3 Relaciones Críticas

```
USUARIOS ←→ SESIONES_JWT (1:N)
USUARIOS ←→ AUDITORIA (1:N)
CLIENTES ←→ SERVICIOS (1:N)
CLIENTES ←→ CUENTAS_COBRO (1:N)
TECNICOS ←→ SERVICIOS (1:N)
TECNICOS ←→ BLOQUEOS_AGENDA (1:N)
SERVICIOS ←→ SERVICIOS_ITEMS (1:N)
SERVICIOS ←→ CUENTAS_COBRO (1:N)
PARAMETROS_COTIZADOR ←→ EQUIPOS (N:1)
```

---

## 5. Arquitectura del Frontend

### 5.1 Estructura de Componentes

- **Pages**: Rutas principales (Layout wrapper)
- **Components**: Componentes reutilizables
- **Hooks**: Lógica compartida (useAuth, useFetch, etc.)
- **Store**: Redux (autenticación, servicios, notificaciones)
- **API**: Capa Axios centralizada
- **Utils**: Utilidades generales

### 5.2 Protección de Rutas

```javascript
<Route element={<ProtectedRoute />}>
  <Route path="/admin" element={<AdminLayout />} />
  <Route path="/tecnico" element={<TecnicoLayout />} />
  <Route path="/cliente" element={<ClienteLayout />} />
</Route>
```

### 5.3 Manejo de Estado

```
Redux Store
├── auth (usuario, token, rol)
├── usuarios (lista, formulario)
├── servicios (lista, detalle)
├── cuentas (lista, detalle)
└── notificaciones (alertas)
```

---

## 6. Autenticación y Sesiones

### 6.1 Flujo de Login

```
1. Usuario ingresa email + contraseña
2. POST /api/v1/auth/login
3. Backend:
   - Validar email existe
   - Verificar contraseña con bcrypt
   - Generar JWT access_token (1h) y refresh_token (7d)
   - Crear registro en SESIONES_JWT
   - Retornar tokens
4. Frontend:
   - Guardar tokens en localStorage/sessionStorage
   - Guardar userData en Redux
   - Redireccionar a dashboard según rol
```

### 6.2 Gestión de Sesiones

- **Token Refresh**: POST /api/v1/auth/refresh
- **Logout**: POST /api/v1/auth/logout (invalida SESIONES_JWT)
- **Timeout**: 15 min inactividad → pedir refresh automático
- **Multi-sesión**: Múltiples tokens por usuario permitidos

---

## 7. Generación de Documentos

### 7.1 PDFs (Cuentas de Cobro)

- **Librería**: TCPDF
- **Ruta**: /api/v1/cuentas/{id}/pdf
- **Almacenamiento**: storage/pdfs/cuentas_cobro/CC-YYYY-XXXX.pdf
- **Contenido**:
  - Encabezado empresa
  - Datos cliente
  - Línea de items
  - Totales (subtotal, IVA, descuento, total)
  - Términos de pago
  - Pie de página

### 7.2 QR Dinámico

- **Librería**: PHP QR Code
- **Contenido**: URL de verificación
- **Formato**: `https://intermica.verifica.io/cuenta/{id}/verify`
- **Almacenamiento**: storage/qr/CC-YYYY-XXXX.png

---

## 8. Logs y Auditoría

### 8.1 Sistema de Logs

- **Librería**: Monolog
- **Canales**:
  - `app.log` - Aplicación general
  - `auditoria.log` - Cambios de datos
  - `errores.log` - Excepciones
- **Formato**: JSON con timestamp, nivel, mensaje

### 8.2 Tabla AUDITORIA

```sql
CREATE TABLE auditoria (
  id INT PRIMARY KEY AUTO_INCREMENT,
  entidad_tipo VARCHAR(50),
  entidad_id INT,
  usuario_id INT,
  accion ENUM('crear', 'actualizar', 'eliminar'),
  estado_anterior JSON,
  estado_nuevo JSON,
  detalles JSON,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);
```

---

## 9. Validación y Manejo de Errores

### 9.1 Validación

- **Backend**: Validadores específicos por entidad
- **Frontend**: React Hook Form + Yup
- **Formato**: JSON con errores por campo

### 9.2 Respuesta de Error

```json
{
  "success": false,
  "status": 400,
  "timestamp": "2026-05-11T20:47:46Z",
  "message": "Validación fallida",
  "errors": {
    "email": ["Email inválido"],
    "contrasena": ["Mínimo 8 caracteres"]
  }
}
```

---

## 10. DevOps y Deployment

### 10.1 Estructura de Entornos

- **local**: Desarrollo con XAMPP
- **development**: Servidor de pruebas
- **staging**: Pre-producción
- **production**: Entorno vivo

### 10.2 Variables por Entorno

Ver .env.example en backend y frontend

---

## 11. Checklist de Implementación

- [ ] Base de datos creada con todas las migraciones
- [ ] Backend: Controllers, Services, Validators
- [ ] Autenticación: JWT, RBAC, Bcrypt
- [ ] Frontend: Layouts por rol, protección rutas
- [ ] Integraciones: PDFs, QR, Auditoría
- [ ] Tests unitarios y e2e
- [ ] Documentación API (Postman/Swagger)
- [ ] Deployment en servidor

---

**Revisión**: 2026-05-11
**Próxima revisión**: 2026-08-11
