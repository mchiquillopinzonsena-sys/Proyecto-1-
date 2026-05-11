# 🔐 Seguridad - Intérmica S.A.S

## Autenticación con JWT (JSON Web Tokens)

### Configuración

**archivo: backend/.env**
```
JWT_SECRET=tu-clave-super-secreta-minimo-32-caracteres
JWT_ALGORITHM=HS256
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800
```

### Flujo de Autenticación

1. **Login**
   ```
   POST /api/v1/auth/login
   Body: { email, password }
   ↓
   Validar credenciales
   Hash bcrypt verificado
   ↓
   Generar JWT con claims: sub, role, iat, exp
   ↓
   Guardar en tabla SESIONES_JWT
   ↓
   Responder: { access_token, refresh_token, expires_in }
   ```

2. **Request Autenticado**
   ```
   Header: Authorization: Bearer {access_token}
   ↓
   Middleware valida firma JWT
   ↓
   Extrae claims: user_id, role
   ↓
   Verifica en SESIONES_JWT que sea activa
   ↓
   Continúa con request
   ```

3. **Refresh Token**
   ```
   POST /api/v1/auth/refresh
   Body: { refresh_token }
   ↓
   Valida refresh_token
   ↓
   Genera nuevo access_token
   ↓
   Mantiene sesión activa
   ```

### Implementación PHP

```php
// app/Helpers/JWTHelper.php
namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper {
    private static $secret;
    private static $algorithm = 'HS256';
    
    public static function generate($usuario) {
        self::$secret = $_ENV['JWT_SECRET'];
        
        $issuedAt = time();
        $expire = $issuedAt + $_ENV['JWT_EXPIRY'];
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $usuario->id,
            'email' => $usuario->email,
            'rol' => $usuario->rol,
            'nombre' => $usuario->nombre,
        ];
        
        return JWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function validate($token) {
        try {
            self::$secret = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode(
                $token,
                new Key(self::$secret, self::$algorithm)
            );
            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

## Control de Acceso Basado en Roles (RBAC)

### Roles Disponibles

| Rol | Permisos | Acceso |
|-----|----------|--------|
| **admin** | CRUD usuarios, Parámetros, Reportes | `/admin/*` |
| **tecnico** | Agenda, Servicios, Registro | `/tecnico/*` |
| **cliente** | Cotizaciones, Cuentas propias | `/cliente/*` |

### Middleware RBAC

```php
// app/Middleware/RBACMiddleware.php
namespace App\Middleware;

use App\Exceptions\ForbiddenException;

class RBACMiddleware {
    private $rolesPermitidos = [
        'GET /usuarios' => ['admin'],
        'POST /usuarios' => ['admin'],
        'PUT /usuarios' => ['admin'],
        'GET /parametros' => ['admin'],
        'PUT /parametros' => ['admin'],
        'GET /tecnicos' => ['admin', 'tecnico'],
        'GET /tecnicos/agenda' => ['admin', 'tecnico'],
        'POST /tecnicos/bloqueos' => ['admin', 'tecnico'],
        'GET /servicios' => ['admin', 'tecnico', 'cliente'],
        'POST /servicios' => ['admin', 'cliente'],
        'PUT /servicios' => ['admin', 'tecnico'],
        'GET /cuentas' => ['admin', 'cliente'],
        'POST /cuentas' => ['admin'],
        'GET /reportes' => ['admin'],
    ];
    
    public function handle($request, $next) {
        $metodo = $request->getMethod();
        $ruta = str_replace('/api/v1', '', $request->getPath());
        $clave = "{$metodo} {$ruta}";
        
        if (!isset($this->rolesPermitidos[$clave])) {
            return $next($request); // Ruta pública
        }
        
        $usuarioRol = $request->usuario->rol;
        $rolesPermitidos = $this->rolesPermitidos[$clave];
        
        if (!in_array($usuarioRol, $rolesPermitidos)) {
            throw new ForbiddenException(
                "Tu rol ({$usuarioRol}) no tiene permiso para: {$clave}"
            );
        }
        
        return $next($request);
    }
}
```

## Hashing de Contraseñas (bcrypt)

### Registro

```php
// app/Services/AuthService.php
public function registrar($datos) {
    $datos['password'] = password_hash(
        $datos['password'],
        PASSWORD_BCRYPT,
        ['cost' => 12] // Iteraciones
    );
    
    return Usuario::create($datos);
}
```

### Validación en Login

```php
public function login($email, $password) {
    $usuario = Usuario::where('email', $email)->first();
    
    if (!$usuario || !password_verify($password, $usuario->password)) {
        throw new AuthException('Credenciales inválidas');
    }
    
    $accessToken = JWTHelper::generate($usuario);
    $refreshToken = $this->generarRefreshToken($usuario);
    
    // Guardar sesión
    SesionJWT::create([
        'usuario_id' => $usuario->id,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'fecha_expiracion' => now()->addHours(1),
        'activa' => 1,
    ]);
    
    return [
        'usuario' => $usuario,
        'tokens' => [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
        ]
    ];
}
```

## CORS (Cross-Origin Resource Sharing)

### Configuración

```php
// app/Middleware/CORSMiddleware.php
namespace App\Middleware;

class CORSMiddleware {
    public function handle($request, $next) {
        header('Access-Control-Allow-Origin: ' . $_ENV['FRONTEND_URL']);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        return $next($request);
    }
}
```

**Variables de entorno:**
```
FRONTEND_URL=http://localhost:3000
API_URL=http://localhost/intermica-api
```

## Validación y Sanitización de Inputs

### Middleware de Validación

```php
// app/Middleware/ValidationMiddleware.php
public function handle($request, $next) {
    // Sanitizar inputs
    $data = $request->all();
    $data = $this->sanitizar($data);
    $request->merge($data);
    
    return $next($request);
}

private function sanitizar($data) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = $this->sanitizar($value);
        } else {
            // Evitar inyecciones
            $sanitized[$key] = htmlspecialchars(
                trim($value),
                ENT_QUOTES,
                'UTF-8'
            );
        }
    }
    
    return $sanitized;
}
```

### Validadores por Entidad

```php
// app/Validators/UsuarioValidator.php
class UsuarioValidator {
    public static function crearRules() {
        return [
            'email' => 'required|email|unique:usuarios',
            'password' => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/',
            'nombre' => 'required|string|max:255',
            'rol' => 'required|in:admin,tecnico,cliente',
        ];
    }
    
    public static function actualizarRules($usuarioId) {
        return [
            'email' => "required|email|unique:usuarios,email,{$usuarioId}",
            'nombre' => 'required|string|max:255',
            'rol' => 'required|in:admin,tecnico,cliente',
        ];
    }
}
```

## Protección contra Vulnerabilidades Comunes

### 1. SQL Injection
✅ **Mitigado con PDO Prepared Statements**
```php
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
$stmt->execute([$email];
```

### 2. XSS (Cross-Site Scripting)
✅ **Mitigado con sanitización y headers de seguridad**
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'');
```

### 3. CSRF (Cross-Site Request Forgery)
✅ **Mitigado con tokens CSRF en formularios**
```php
// En formularios
<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

// Validación
if ($request->post('csrf_token') !== $_SESSION['csrf_token']) {
    throw new ValidationException('Token CSRF inválido');
}
```

### 4. Rate Limiting
✅ **Implementado en Auth endpoints**
```php
// app/Middleware/RateLimitMiddleware.php
private $limites = [
    '/auth/login' => ['5 requests per 15 minutes'],
    '/auth/register' => ['3 requests per hour'],
];
```

## Configuración de Seguridad en Producción

### Backend (.env production)
```
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=<generar-clave-aleatoria-segura>
DB_PASS=<contraseña-mysql-fuerte>
FRONTEND_URL=https://intermica.com.co
API_URL=https://api.intermica.com.co
```

### Frontend (.env.production)
```
REACT_APP_API_URL=https://api.intermica.com.co
REACT_APP_ENV=production
REACT_APP_ENABLE_LOGS=false
```

### Servidor HTTPS
```
# .htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

## Monitoreo de Seguridad

### Logs de Auditoría
```sql
SELECT * FROM auditoria
WHERE accion IN ('crear', 'actualizar', 'eliminar')
AND fecha_hora > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY fecha_hora DESC;
```

### Detección de Anomalías
```php
// app/Services/SecurityService.php
public function detectarActividadSospechosa() {
    // Múltiples intentos fallidos de login
    $intentosFallidos = LoginIntento::where(
        'fecha_hora',
        '>',
        now()->subMinutes(15)
    )->where('exitoso', 0)->count();
    
    if ($intentosFallidos > 5) {
        // Bloquear IP
        IPBloqueada::create(['ip' => request()->ip()]);
        Log::warning('IP bloqueada por intentos fallidos');
    }
}
```

## Checklist de Seguridad

- [x] JWT con expiración
- [x] RBAC implementado
- [x] Bcrypt para contraseñas
- [x] Sanitización de inputs
- [x] SQL Injection mitigado
- [x] XSS mitigado
- [x] CORS configurado
- [x] Headers de seguridad
- [x] Logs de auditoría
- [x] Rate limiting
- [x] Borrado lógico
- [x] ON DELETE RESTRICT
- [x] HTTPS en producción
