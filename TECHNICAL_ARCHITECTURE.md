# 📐 Documento Técnico de Arquitectura v2.0
## Plataforma Operativa Intérmica S.A.S

**Versión:** 2.0  
**Fecha:** 2026-05-11  
**Estado:** ✅ Aprobado para Implementación

---

## 1. VISIÓN ARQUITECTÓNICA

### 1.1 Objetivos de Arquitectura

- **Escalabilidad**: Soportar múltiples técnicos y clientes concurrentes
- **Seguridad**: Autenticación JWT + RBAC con auditoría completa
- **Confiabilidad**: Integridad de datos con 5NF y borrado lógico
- **Mantenibilidad**: Código modular, testeable y documentado
- **Performance**: Respuestas API < 200ms en operaciones normales

### 1.2 Principios de Diseño

1. **Separación de Capas**: Controllers → Services → Models → DB
2. **Responsabilidad Única**: Cada clase una tarea específica
3. **DRY (Don't Repeat Yourself)**: Código reutilizable
4. **SOLID Principles**: Especialmente Dependency Injection
5. **Fail-Safe**: Transacciones ACID en operaciones críticas

---

## 2. ARQUITECTURA DE BACKEND (PHP API)

### 2.1 Stack Tecnológico

```
APACHE/NGINX
    ↓
ROUTER (api.php)
    ↓
MIDDLEWARE CHAIN
├── CORS
├── Authentication (JWT)
├── RBAC
├── Validation
└── Error Handling
    ↓
CONTROLLERS
    ↓
SERVICES (Reglas de Negocio)
    ↓
MODELS (ORM-like)
    ↓
DATABASE (MySQL/MariaDB)
```

### 2.2 Flujo de Autenticación JWT

```
1. POST /api/v1/auth/login
   Request: { email, password }
           ↓
2. AuthController → AuthService
   - Validar email existe
   - bcrypt_verify(password, hash_almacenado)
           ↓
3. Si OK:
   - Generar JWT (exp: 3600s)
   - Generar Refresh Token (exp: 7 días)
   - Crear registro en SESIONES_JWT
   - Retornar tokens
           ↓
4. Cliente almacena: Authorization: Bearer {access_token}
           ↓
5. En cada request:
   - AuthMiddleware valida JWT
   - Si expirado: usa refresh_token para nuevo access_token
   - Si inválido: 401 Unauthorized
```

### 2.3 RBAC (Role-Based Access Control)

```php
// Roles disponibles
Admin    → Acceso completo
Técnico  → Servicios, agenda, reportes personales
Cliente  → Cotizaciones, cuentas propias, perfil
Guest    → Solo login

// Implementación en Middleware
Route::middleware([AuthMiddleware::class, RBACMiddleware::class])
  ->group(function() {
    Route::get('/admin/*', [AdminController::class, 'index'])
         ->requireRole('admin');
  });
```

### 2.4 Estructura de Servicios (Business Logic)

```php
Services/ (
laravel el contexto de las reglas de negocio)
├── AuthService          → JWT, bcrypt, sesiones
├── UsuarioService       → CRUD usuarios
├── TecnicoService       → Gestión técnicos
├── ClienteService       → Gestión clientes
├── ServicioService      → Servicios, cotizaciones
├── CuentaCobroService   → RN-06 (Generación CC)
├── StockService         → RN-02 (Actualización automática)
├── AgendaService        → RN-13/14 (Validación bloqueos)
├── DocumentoService     → PDFs + QR dinámicos
├── AuditoriaService     → RN-16 (Logs transiciones)
└── ParametrosService    → PARAMETROS_COTIZADOR
```

### 2.5 Manejo de Errores y Logs

```
Exceptions Personalizadas:
├── AppException         → General (500)
├── ValidationException  → Validación fallida (422)
├── AuthException        → Auth fallida (401)
├── ForbiddenException   → Sin permisos (403)
└── NotFoundException    → Recurso no existe (404)

Logs de Auditoría (auditoria.log):
- Cambios de estado (RN-16)
- Transacciones de stock (RN-02)
- Creación de cuentas de cobro (RN-06)
- Accesos no autorizados
- Errores críticos
```

---

## 3. ARQUITECTURA DE FRONTEND (React)

### 3.1 Stack Tecnológico

```
VITE (Build Tool)
    ↓
REACT 18 (Component-based)
    ↓
REDUX TOOLKIT (State Management)
    ↓
REACT ROUTER v6 (Routing)
    ↓
AXIOS (HTTP Client)
    ↓
COMPONENTS
├── Pages (Vistas completas)
├── Components (Reutilizables)
├── Hooks (Lógica compartida)
└── Utils (Helpers)
```

### 3.2 Protección de Rutas por Rol

```jsx
// ProtectedRoute.jsx
<Route
  path="/admin/*"
  element={<ProtectedRoute roles={['admin']}>
    <AdminLayout />
  </ProtectedRoute>}
/>

// RoleBasedRoute valida:
1. JWT válido en localStorage
2. Token no expirado
3. Usuario tiene rol requerido
4. Si falla → redirige a /login
```

### 3.3 Estructura de API Client

```javascript
// api/client.js
const axiosInstance = axios.create({
  baseURL: process.env.REACT_APP_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${getToken()}`
  }
});

// Interceptores:
// - Request: agregar JWT automáticamente
// - Response: manejar 401, refrescar token
// - Error: mostrar notificaciones
```

### 3.4 State Management (Redux)

```javascript
Store Layout:
├── auth           → Usuario, tokens, rol
├── usuarios       → Lista, paginación
├── servicios      → Servicios, cotizaciones
├── cuentas        → Cuentas de cobro
├── notificaciones → Toast messages
└── ui             → Loading, modal states

// Patrón Slice:
each slice = {
  initialState,
  reducers: { },
  extraReducers: { } // para async thunks
}
```

---

## 4. BASE DE DATOS (MySQL 5.7+)

### 4.1 Normalización 5NF

Todas las tablas cumplen 5 Formas Normales:
- ✅ 1NF: Valores atómicos
- ✅ 2NF: Dependencia funcional completa
- ✅ 3NF: Sin dependencias transitivas
- ✅ BCNF: Determinantes son claves candidatas
- ✅ 4NF/5NF: Sin dependencias multivaluadas

### 4.2 Tablas Principales

```sql
-- RN-23: Borrado Lógico (activo = 0/1)
USUARIOS
├── id (PK)
├── email (UNIQUE)
├── nombre
├── password_hash (bcrypt)
├── rol (admin, tecnico, cliente)
├── activo (0=inactivo, 1=activo)
└── timestamps

SESIONES_JWT
├── id (PK)
├── usuario_id (FK) → USUARIOS
├── token_hash
├── ip_address
├── user_agent
├── fecha_inicio
├── fecha_expiracion
└── activa (0=revocada, 1=activa)

TECNICOS
├── id (PK)
├── usuario_id (FK→USUARIOS, UNIQUE)
├── especialidad
├── disponible (0/1)
├── activo (0/1)
└── timestamps

CLIENTES
├── id (PK)
├── usuario_id (FK→USUARIOS, UNIQUE)
├── empresa
├── nit (UNIQUE)
├── telefono
├── direccion
├── activo (0/1)
└── timestamps

SERVICIOS
├── id (PK)
├── numero (CC-YYYY-XXXX)
├── cliente_id (FK)
├── tecnico_asignado_id (FK→TECNICOS, NULL)
├── estado (cotización, agendado, en_ejecución, completado, cancelado)
├── fecha_servicio
├── descripcion
├── valor_estimado
├── activo (0/1)
└── timestamps

CUENTAS_COBRO (RN-06)
├── id (PK)
├── numero (CC-YYYY-XXXX, UNIQUE) ← Auto-generado
├── cliente_id (FK)
├── fecha_emision
├── fecha_vencimiento
├── subtotal
├── iva
├── total
├── estado (pendiente, pagada, vencida, cancelada)
├── activo (0/1, RN-23)
└── timestamps

PARAMETROS_COTIZADOR
├── id (PK)
├── codigo (UNIQUE)
├── tipo (precio_base, factor_complejidad, etc.)
├── valor
├── activo (0/1)
└── timestamps

STOCK (RN-02)
├── id (PK)
├── codigo_parte (UNIQUE)
├── descripcion
├── cantidad_actual
├── cantidad_minima
├── precio_unitario
├── activo (0/1)
└── timestamps

MOVIMIENTOS_STOCK
├── id (PK)
├── stock_id (FK)
├── tipo (entrada, salida)
├── cantidad
├── razon
├── servicio_id (FK, NULL)
├── usuario_id (FK)
└── timestamps

BLOQUEOS_AGENDA (RN-13/14)
├── id (PK)
├── tecnico_id (FK)
├── fecha_inicio
├── fecha_fin
├── razon
├── activo (0/1)
└── timestamps

AUDITORIA (RN-16)
├── id (PK)
├── usuario_id (FK)
├── accion (crear, actualizar, eliminar, cambio_estado)
├── tabla_afectada
├── registro_id
├── datos_anteriores (JSON)
├── datos_nuevos (JSON)
├── ip_address
└── timestamp
```

### 4.3 Integridad Referencial (RN-25)

```sql
-- ON DELETE RESTRICT protege datos históricos
ALTER TABLE SESIONES_JWT
ADD CONSTRAINT fk_usuario_id
FOREIGN KEY (usuario_id) REFERENCES USUARIOS(id)
ON DELETE RESTRICT
ON UPDATE CASCADE;

-- Si usuario tiene sesiones activas → no puede eliminarse
-- Usar: UPDATE usuarios SET activo=0 (borrado lógico)
```

---

## 5. REGLAS DE NEGOCIO IMPLEMENTADAS

### RN-02: Actualización Automática de Stock

```php
// StockService.php
public function decrementarStock($servicio_id, $items) {
    DB::beginTransaction();
    
    foreach ($items as $item) {
        $stock = Stock::find($item['stock_id']);
        
        if ($stock->cantidad_actual < $item['cantidad']) {
            throw new StockInsuficienteException();
        }
        
        $stock->update([
            'cantidad_actual' => $stock->cantidad_actual - $item['cantidad']
        ]);
        
        // Log movimiento
        MovimientoStock::create([
            'stock_id' => $stock->id,
            'tipo' => 'salida',
            'cantidad' => $item['cantidad'],
            'servicio_id' => $servicio_id,
            'usuario_id' => auth()->id()
        ]);
    }
    
    DB::commit();
}
```

### RN-06: Generación Automática de Cuentas de Cobro

```php
// CuentaCobroService.php
public function generarCuenta($cliente_id, $servicio_ids) {
    // Formato: CC-YYYY-XXXX (CC=Cuenta Cobro, YYYY=año, XXXX=secuencial)
    $year = date('Y');
    $secuencial = CuentaCobro::whereYear('created_at', $year)
        ->max('numero') + 1;
    $numero = "CC-{$year}-" . str_pad($secuencial, 4, '0', STR_PAD_LEFT);
    
    $cuenta = CuentaCobro::create([
        'numero' => $numero,
        'cliente_id' => $cliente_id,
        'fecha_emision' => now(),
        'fecha_vencimiento' => now()->addDays(30),
        'estado' => 'pendiente'
    ]);
    
    // Agregar items
    $total = 0;
    foreach ($servicio_ids as $servicio_id) {
        $servicio = Servicio::find($servicio_id);
        $subtotal = $servicio->valor_estimado;
        $iva = $subtotal * 0.19;
        
        CuentaCobroItem::create([
            'cuenta_cobro_id' => $cuenta->id,
            'servicio_id' => $servicio_id,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $subtotal + $iva
        ]);
        
        $total += $subtotal + $iva;
    }
    
    $cuenta->update(['total' => $total]);
    
    // Log auditoría
    AuditoriaService::registrar('crear', 'CUENTAS_COBRO', $cuenta->id);
    
    return $cuenta;
}
```

### RN-13/14: Validación y Bloqueo de Agenda

```php
// AgendaService.php
public function verificarDisponibilidad($tecnico_id, $fecha_inicio, $fecha_fin) {
    // Verificar si hay bloqueos
    $bloqueos = BloqueoAgenda::where('tecnico_id', $tecnico_id)
        ->where('fecha_inicio', '<=', $fecha_fin)
        ->where('fecha_fin', '>=', $fecha_inicio)
        ->where('activo', 1)
        ->exists();
    
    if ($bloqueos) {
        throw new TecnicoNoDisponibleException('Técnico bloqueado en esas fechas');
    }
    
    // Verificar servicios existentes
    $conflictos = Servicio::where('tecnico_asignado_id', $tecnico_id)
        ->whereIn('estado', ['agendado', 'en_ejecución'])
        ->where('fecha_servicio', '<=', $fecha_fin)
        ->where('fecha_fin', '>=', $fecha_inicio)
        ->exists();
    
    if ($conflictos) {
        throw new ConflictoAgendaException('Técnico tiene otro servicio en esa fecha');
    }
    
    return true;
}
```

### RN-16: Auditoría de Transiciones de Estado

```php
// AuditoriaService.php
public function registrarCambioEstado($tabla, $registro_id, $estado_anterior, $estado_nuevo) {
    Auditoria::create([
        'usuario_id' => auth()->id(),
        'accion' => 'cambio_estado',
        'tabla_afectada' => $tabla,
        'registro_id' => $registro_id,
        'datos_anteriores' => json_encode(['estado' => $estado_anterior]),
        'datos_nuevos' => json_encode(['estado' => $estado_nuevo]),
        'ip_address' => request()->ip(),
        'timestamp' => now()
    ]);
}

// Uso en Services:
$servicio->update(['estado' => 'completado']);
AuditoriaService::registrarCambioEstado('SERVICIOS', $servicio->id, 'en_ejecución', 'completado');
```

### RN-23: Borrado Lógico

```php
// BaseModel.php - Trait
trait BorradoLogico {
    public function scopeActivos($query) {
        return $query->where('activo', 1);
    }
    
    public function borrarLogico() {
        return $this->update(['activo' => 0]);
    }
}

// Uso:
$usuario->borrarLogico(); // No elimina, solo marca inactivo
```

### RN-25: Integridad Referencial

```sql
-- Las FKs están configuradas con ON DELETE RESTRICT
-- Esto previene eliminaciones accidentales que romperían auditoría
-- Usar borrado lógico (RN-23) para desactivar usuarios
```

---

## 6. SEGURIDAD

### 6.1 Autenticación

- ✅ **JWT (JSON Web Tokens)**
  - Algorithm: HS256
  - Lifetime: 1 hora
  - Refresh token: 7 días
  - Almacenado en `SESIONES_JWT`

- ✅ **Contraseñas**
  - Algoritmo: bcrypt (PHP password_hash)
  - Cost factor: 12
  - Nunca en logs ni en auditoría

### 6.2 Autorización (RBAC)

- ✅ 3 Roles principales: Admin, Técnico, Cliente
- ✅ Middleware valida rol en cada request
- ✅ URLs protegidas retornan 403 si rol insuficiente

### 6.3 Inyección SQL

- ✅ Prepared statements en todas las queries
- ✅ Validación de entrada en Validators
- ✅ Escape de output en respuestas JSON

### 6.4 XSS (Cross-Site Scripting)

- ✅ Content-Security-Policy headers
- ✅ Sanitización de input en formularios React
- ✅ JSON encode de datos en respuestas

### 6.5 CORS

```php
// Configurado en CORSMiddleware.php
CORS_ORIGIN=http://localhost:3000
Allowed methods: GET, POST, PUT, DELETE, PATCH
Allowed headers: Content-Type, Authorization
```

---

## 7. INTEGRACIÓN FRONTEND-BACKEND

### 7.1 Flujo de Solicitud

```
React Component
     ↓
Dispatch Redux Action
     ↓
Axios Request (api/client.js)
     ↓
[Authorization: Bearer JWT]
     ↓
Backend Router (routes/api.php)
     ↓
Middleware Chain
├── CORS
├── Auth
├── RBAC
└── Validation
     ↓
Controller
     ↓
Service (Reglas de Negocio)
     ↓
Model → Database
     ↓
Response JSON
     ↓
Axios Interceptor
     ↓
Redux Store Update
     ↓
Component Re-render
```

### 7.2 Manejo de Errores

```javascript
// Frontend
try {
    const response = await api.post('/servicios', data);
    dispatch(servicioCreado(response.data.data));
} catch (error) {
    if (error.response?.status === 401) {
        // Token expirado, refrescar
        const newToken = await refreshToken();
    } else if (error.response?.status === 422) {
        // Validación fallida, mostrar errores
        setErrors(error.response.data.errors);
    } else {
        // Error general
        showNotification('Error al crear servicio');
    }
}
```

---

## 8. DEPLOYMENT

### 8.1 Requisitos Producción

- PHP 8.1+ con extensiones: PDO, JSON, BCrypt
- MySQL 5.7+ o MariaDB 10.3+
- HTTPS obligatorio
- SSL/TLS con certificado válido
- Firewall configurado

### 8.2 Checklist Pre-Deploy

```
☐ APP_ENV=production
☐ APP_DEBUG=false
☐ JWT_SECRET cambio a valor seguro (64 caracteres)
☐ DB contraseña fuerte
☐ CORS_ORIGIN ajustado a dominio real
☐ Logs dirigidos a cloud (CloudWatch, Papertrail)
☐ Backups automáticos de BD habilitados
☐ HTTPS/SSL certificado instalado
☐ Rate limiting configurado
☐ DDoS protection activado
```

---

## 9. MONITOREO Y OBSERVABILIDAD

### 9.1 Logs

```
app.log       → Actividad general
auditoria.log → Cambios de estado, accesos
errores.log   → Stack traces, excepciones
```

### 9.2 Métricas (sugerido: Prometheus)

- Tiempo de respuesta API
- Errores por endpoint
- Uso de base de datos
- Sesiones activas
- Transacciones completadas

---

## 10. EVOLUCIÓN FUTURA

- [ ] Notificaciones en tiempo real (WebSockets)
- [ ] Dashboard analítico avanzado
- [ ] Integración con pasarela de pagos
- [ ] App móvil nativa (React Native)
- [ ] Machine Learning para predicción de mantenimiento
- [ ] Integración con ERP (SAP, Odoo)

---

**Documento preparado por:** Arquitecto de Sistemas  
**Aprobado por:** Liderazgo Técnico  
**Vigencia:** 2026
