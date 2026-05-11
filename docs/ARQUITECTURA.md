# 🏗️ Arquitectura Intérmica S.A.S

## Visión General

Plataforma fullstack con separación clara entre frontend (React) y backend (PHP), siguiendo patrones de arquitectura limpia.

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENTE (Browser)                        │
│  ┌────────────────────────────────────────────────────────┐  │
│  │          React Application (JavaScript)                │  │
│  │  - Redux Store (Estado global)                         │  │
│  │  - Componentes por roles (Admin/Técnico/Cliente)       │  │
│  │  - Hooks personalizados (useAuth, useFetch)            │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────┬───────────────────────────────────────┘
                       │ HTTPS/HTTP
                       │ JWT en Headers
                       │
┌──────────────────────▼───────────────────────────────────────┐
│                    API RESTful (PHP)                          │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Middleware Stack                                       │  │
│  │ ├─ CORS Middleware                                     │  │
│  │ ├─ Auth Middleware (JWT Validation)                    │  │
│  │ ├─ RBAC Middleware (Role-based Access)                 │  │
│  │ ├─ Validation Middleware (Input sanitization)          │  │
│  │ ├─ Error Handler Middleware                            │  │
│  │ └─ Logging Middleware (Auditoria)                      │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Routers                                                │  │
│  │ ├─ /api/v1/auth                                        │  │
│  │ ├─ /api/v1/usuarios                                    │  │
│  │ ├─ /api/v1/tecnicos                                    │  │
│  │ ├─ /api/v1/servicios                                   │  │
│  │ ├─ /api/v1/cuentas                                     │  │
│  │ └─ /api/v1/reportes                                    │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Controllers (Lógica de Solicitud)                      │  │
│  │ ├─ AuthController                                      │  │
│  │ ├─ UsuariosController                                  │  │
│  │ ├─ ServiciosController                                 │  │
│  │ ├─ CuentasController                                   │  │
│  │ └─ ReportesController                                  │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Services (Reglas de Negocio)                           │  │
│  │ ├─ AuthService (JWT, RBAC, bcrypt)                     │  │
│  │ ├─ StockService (RN-02: Auto update)                   │  │
│  │ ├─ CuentaCobroService (RN-06: CC-YYYY-XXXX)            │  │
│  │ ├─ AgendaService (RN-13/14: Validación técnicos)       │  │
│  │ ├─ DocumentoService (PDF, QR dinámico)                 │  │
│  │ ├─ AuditoriaService (RN-16: Transiciones)              │  │
│  │ └─ ParametrosService (Cotizador inteligente)           │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Models (Entidades & Persistencia)                      │  │
│  │ ├─ Usuario                                             │  │
│  │ ├─ Tecnico                                             │  │
│  │ ├─ Cliente                                             │  │
│  │ ├─ Servicio                                            │  │
│  │ ├─ CuentaCobro                                         │  │
│  │ ├─ SesionJWT                                           │  │
│  │ └─ Auditoria                                           │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────┬───────────────────────────────────────┘
                       │ PDO (MySQL/MariaDB)
                       │
┌──────────────────────▼───────────────────────────────────────┐
│                    MySQL/MariaDB                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Tablas (5NF - Normalización)                           │  │
│  │ ├─ usuarios                                            │  │
│  │ ├─ sesiones_jwt                                        │  │
│  │ ├─ tecnicos                                            │  │
│  │ ├─ clientes                                            │  │
│  │ ├─ servicios                                           │  │
│  │ ├─ servicios_items                                     │  │
│  │ ├─ cuentas_cobro                                       │  │
│  │ ├─ cuentas_items                                       │  │
│  │ ├─ parametros_cotizador                                │  │
│  │ ├─ stock                                               │  │
│  │ ├─ movimientos_stock                                   │  │
│  │ ├─ bloqueos_agenda                                     │  │
│  │ ├─ auditoria                                           │  │
│  │ └─ configuracion_empresa                               │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Vistas (para reportes)                                 │  │
│  │ ├─ v_servicios_completados                             │  │
│  │ ├─ v_ingresos_mensuales                                │  │
│  │ └─ v_agenda_tecnicos                                   │  │
│  │                                                         │  │
│  │ Triggers (Automaciones)                                │  │
│  │ ├─ tg_actualizar_stock (RN-02)                         │  │
│  │ ├─ tg_generar_cuenta_cobro (RN-06)                     │  │
│  │ └─ tg_auditoria_cambios (RN-16)                        │  │
│  └────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

## Patrones Implementados

### 1. MVC (Model-View-Controller)
- **Models**: Entidades que representan tablas DB
- **Views**: Componentes React
- **Controllers**: Orquestación de lógica

### 2. Repository Pattern (Backend)
```php
class UsuarioRepository {
    public function findById($id) { ... }
    public function findAll() { ... }
    public function create($data) { ... }
    public function update($id, $data) { ... }
}
```

### 3. Service Layer (Reglas de Negocio)
```php
class CuentaCobroService {
    public function generarCuenta($servicioIds) {
        // RN-06: Generar número CC-YYYY-XXXX
        // RN-02: Actualizar stock
        // RN-16: Auditar transición de estado
    }
}
```

### 4. Middleware Pipeline (PHP)
```php
$middleware = [
    CORSMiddleware::class,
    AuthMiddleware::class,
    RBACMiddleware::class,
    ValidationMiddleware::class,
    ErrorHandlerMiddleware::class,
    LoggingMiddleware::class,
];
```

### 5. Redux Store (Frontend)
```javascript
// State Management
store = {
  auth: { user, tokens, isAuthenticated },
  usuarios: { list, loading, error },
  servicios: { list, currentServicio, loading },
  cuentas: { list, currentCuenta, loading },
}
```

## Flujos de Datos

### Login Flow
```
Login Form → useAuth Hook → API /auth/login → JWT + User → Redux Store → Protected Routes
```

### Servicio Completo Flow
```
Servicio Creado
  ↓ (RN-06) Generar Cuenta Cobro automática
  ↓ (RN-02) Actualizar Stock automático
  ↓ (RN-16) Auditar transición de estado
  ↓ (RN-13/14) Validar disponibilidad técnico
  ↓ Generar PDF + QR Dinámico
  ↓ Notificar a cliente
```

### Seguridad
```
Request → Validar JWT → Extraer rol → Verificar RBAC → Ejecutar → Auditar
```

## Capas de Validación

### 1. Frontend
- React Hook Form + Yup (validación de esquema)
- Validación en tiempo real
- Mensajes de error visuales

### 2. Backend (Input)
- Middleware de validación
- Sanitización de inputs
- Type hints de PHP 8.1

### 3. Base de Datos
- Constraints (PK, FK, UNIQUE)
- Triggers para reglas complejas
- ON DELETE RESTRICT (RN-25)

## Integridad de Datos

### Borrado Lógico (RN-23)
```sql
-- En lugar de DELETE
UPDATE usuarios SET activo = 0 WHERE id = $id;

-- Todas las queries incluyen
WHERE activo = 1;
```

### Restricción de Integridad (RN-25)
```sql
ALTER TABLE servicios
ADD CONSTRAINT fk_tecnico
FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id)
ON DELETE RESTRICT;
```

## Performance

- Índices en FK y campos de búsqueda
- Paginación en listados
- Caching de autenticación (JWT)
- Lazy loading de componentes React
- Compresión de respuestas API

## Escalabilidad Futura

- API Versioning (/api/v1, /api/v2)
- Queue system para PDFs y notificaciones
- Event sourcing para auditoría
- GraphQL layer
- Micro-servicios para dominios específicos
