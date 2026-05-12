# 🔒 Guía de Hardening de Seguridad - Intérmica

**Fecha**: 2026-05-12  
**Versión**: 1.0  
**Estado**: Crítico (Implementar antes de producción)

---

## 1. Configuración HTTPS/TLS

### 1.1 Requerimiento Obligatorio
```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    server_name api.intermica.com;

    # Certificado SSL (Let's Encrypt recomendado)
    ssl_certificate /etc/letsencrypt/live/api.intermica.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.intermica.com/privkey.pem;

    # TLS 1.2+ solo (sin SSLv3, TLS 1.0, 1.1)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # HSTS (Strict Transport Security)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
}

# Redirigir HTTP → HTTPS
server {
    listen 80;
    server_name api.intermica.com;
    return 301 https://$server_name$request_uri;
}
```

### 1.2 Verificación
```bash
# Verificar configuración
nmap --script ssl-enum-ciphers -p 443 api.intermica.com

# Calificar SSL
curl https://www.ssllabs.com/api/analyze?host=api.intermica.com&publish=off&all=on
```

---

## 2. Headers de Seguridad

Todos estos headers ya están configurados en `config/cors.php`:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff                    # Prevenir MIME-sniffing
X-Frame-Options: DENY                              # Prevenir Clickjacking
X-XSS-Protection: 1; mode=block                    # XSS Filter
Content-Security-Policy: default-src 'self';...    # CSP stricta
```

---

## 3. Variables de Entorno - NUNCA en repositorio

### 3.1 Nunca commitear `.env`
```bash
# .gitignore ya incluye:
.env
.env.local
.env.*.local
```

### 3.2 Valores que DEBEN ser secretos
```
JWT_SECRET=<GENERAR_ALEATORIAMENTE>
DB_PASS=<CONTRASEÑA_FUERTE>
MAIL_PASSWORD=<TOKEN_MAILTRAP>
```

### 3.3 Generar secretos fuertes
```bash
# PHP
php -r "echo bin2hex(random_bytes(32));"

# Bash
openssl rand -hex 32
```

---

## 4. Base de Datos

### 4.1 Credenciales
```sql
-- NUNCA usar 'root' con contraseña vacía en producción
CREATE USER 'intermica_user'@'localhost' IDENTIFIED BY 'CONTRASEÑA_FUERTE_32_CARACTERES';
GRANT SELECT, INSERT, UPDATE, DELETE ON intermica_db.* TO 'intermica_user'@'localhost';
```

### 4.2 Conexión
```php
// .env - NUNCA localhost en producción (usar 127.0.0.1 o socket)
DB_HOST=127.0.0.1
DB_USER=intermica_user
DB_PASS=<CONTRASEÑA_FUERTE>
DB_PORT=3306

// Desabilitar acceso remoto
# /etc/mysql/my.cnf
bind-address = 127.0.0.1
```

### 4.3 Backups
```bash
#!/bin/bash
# backup.sh
BACKUP_DIR="/backups/mysql"
mkdir -p $BACKUP_DIR

mysqldump -u root -p$DB_PASS --all-databases > \
    $BACKUP_DIR/intermica_db_$(date +%Y%m%d_%H%M%S).sql

# Encriptar backup
gpg --encrypt $BACKUP_DIR/*.sql

# Rotar backups (mantener últimos 30 días)
find $BACKUP_DIR -type f -mtime +30 -delete
```

---

## 5. Autenticación & Contraseñas

### 5.1 Requerimientos de Contraseña
```php
// Validar en el registro
$rules = [
    'password' => [
        'required',
        'min:12',                           // Mínimo 12 caracteres
        'regex:/[A-Z]/',                    // Al menos 1 mayúscula
        'regex:/[a-z]/',                    // Al menos 1 minúscula
        'regex:/[0-9]/',                    // Al menos 1 número
        'regex:/[!@#$%^&*]/',               // Al menos 1 símbolo
    ],
];
```

### 5.2 Hash de Contraseñas
```php
// ✅ CORRECTO - Ya implementado
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ✅ VERIFICAR
if (password_verify($password, $hash)) {
    // Login exitoso
}
```

### 5.3 Expiración de Contraseñas
```sql
ALTER TABLE usuarios ADD COLUMN
    fecha_cambio_password TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Política: Cambiar cada 90 días
SELECT * FROM usuarios
WHERE fecha_cambio_password < DATE_SUB(NOW(), INTERVAL 90 DAY)
    AND rol IN ('admin', 'tecnico');
```

---

## 6. Control de Acceso

### 6.1 Rate Limiting (Ya implementado)
```
- Login: 5 intentos por 15 minutos
- API General: 100 requests por minuto
- Admin sensible: 10 requests por minuto
```

### 6.2 Bloqueo de Cuenta
```php
// Después de 5 intentos fallidos
UPDATE usuarios SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
WHERE email = ? AND intentos_fallidos >= 5;
```

### 6.3 Permisos Granulares
- ✅ Implementado: Tabla `permisos` + `rol_permisos`
- ✅ RBAC dinámico: RBACService
- ✅ Alcance de cliente: Clientes ven solo sus propios datos

---

## 7. Logging & Auditoría

### 7.1 Logs Estructurados
```php
// ✅ Ya implementado con Monolog
- app.log        → Aplicación general
- auditoria.log  → Acciones de usuarios
- seguridad.log  → Intentos de acceso no autorizado
- errores.log    → Excepciones

// JSON format con timestamp, user_id, ip, user_agent
```

### 7.2 Retención de Logs
```bash
# Rotar logs diariamente, mantener 90 días
logrotate -f /etc/logrotate.d/intermica
```

### 7.3 Monitoreo Alertas
```
- 5+ intentos fallidos de login → EMAIL ADMIN
- Acceso denegado (403) → LOG
- Error interno (500) → EMAIL + PAGERDUTY
```

---

## 8. CORS & CSRF

### 8.1 CORS (Ya implementado)
```
❌ NUNCA: Access-Control-Allow-Origin: *
✅ CORRECTO: Whitelist de dominios en .env
```

### 8.2 CSRF Tokens (TODO)
```php
// Para futuro: Agregar CSRF token validation
// Los formularios deben incluir X-CSRF-Token header
```

### 8.3 SameSite Cookies
```php
// Configuración de sesiones
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
```

---

## 9. Validación de Entrada

### 9.1 Prepared Statements (✅ Ya implementado)
```php
// ✅ CORRECTO
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
$stmt->execute([$email]);

// ❌ NUNCA hacer esto
$sql = "SELECT * FROM usuarios WHERE email = '$email'";
```

### 9.2 Sanitización
```php
// ✅ Validar tipos
$id = (int) $request['id'];
$email = filter_var($request['email'], FILTER_VALIDATE_EMAIL);

// ✅ Limitar tamaño
if (strlen($input) > 255) {
    throw new ValidationException('Input demasiado largo');
}
```

---

## 10. Despliegue Seguro

### 10.1 Checklist Pre-Producción
```bash
- [ ] HTTPS configurado
- [ ] .env con secretos no en git
- [ ] APP_DEBUG=false
- [ ] JWT_SECRET generado aleatoriamente
- [ ] DB backups automatizados
- [ ] Logs rotados y monitoreados
- [ ] CORS whitelist configurado
- [ ] Rate limiting habilitado
- [ ] Firewall configuraado
- [ ] WAF (Web Application Firewall) como Cloudflare
- [ ] Monitoreo en vivo (Sentry, New Relic)
- [ ] Alertas configuradas
```

### 10.2 Firewall
```bash
# UFW (Uncomplicated Firewall)
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 443/tcp  # HTTPS
sudo ufw allow 80/tcp   # HTTP → redirect
sudo ufw deny 3306      # MySQL (local only)
sudo ufw enable
```

### 10.3 Fail2Ban (Prevenir ataques)
```bash
# Instalar
sudo apt install fail2ban

# /etc/fail2ban/jail.local
[sshd]
enabled = true
maxretry = 3
findtime = 600
bantime = 3600

[php-api]
port = http,https
filter = php-api
maxretry = 10
```

---

## 11. Integración Continua (CI/CD)

### 11.1 Pre-deployment checks
```yaml
# .github/workflows/security.yml
name: Security Checks
on: [push, pull_request]

jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      # Verificar secretos no commiteados
      - name: Detect secrets
        run: npm install -g git-secrets && git secrets --install && git secrets --scan

      # SAST (Static Application Security Testing)
      - name: SAST Analysis
        run: php vendor/bin/phpstan analyse app/ --level=max

      # Verificar dependencias vulnerables
      - name: Check dependencies
        run: composer audit
```

---

## 12. Referencias & Recursos

- **OWASP Top 10**: https://owasp.org/Top10/
- **CWE Top 25**: https://cwe.mitre.org/top25/
- **PHP Security**: https://www.php.net/manual/en/security.php
- **JWT Best Practices**: https://tools.ietf.org/html/rfc8725
- **NIST Guidelines**: https://csrc.nist.gov/

---

**PRÓXIMA AUDITORÍA DE SEGURIDAD**: 2026-08-12 (Cada 3 meses)
