<?php
/**
 * CORS Configuration - Whitelist-based security
 * Uses environment variables with sensible defaults for production
 */

return [
    // Allow-List de dominios. Nunca uses '*' en producción
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:3000')
    )),

    // Métodos HTTP permitidos
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],

    // Headers permitidos
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    // Headers expuestos al cliente
    'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],

    // Cache preflight (segundos)
    'max_age' => 86400, // 24 horas

    // Permitir credentials (cookies, auth headers)
    'supports_credentials' => true,

    // Headers de seguridad adicionales
    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';",
    ],
];
