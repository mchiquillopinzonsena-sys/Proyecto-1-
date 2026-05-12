<?php
/**
 * CORS Middleware - Whitelist-based, secure CORS implementation
 *
 * Replaces the insecure "Access-Control-Allow-Origin: *" pattern
 * with a whitelist of trusted origins
 */

namespace App\Middleware;

class CORSMiddleware
{
    private static array $config = [];

    /**
     * Inicializa con configuración
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Aplica headers CORS seguros
     */
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = self::$config['allowed_origins'] ?? [];
        $isAllowed = false;

        // Validar origen contra whitelist
        foreach ($allowedOrigins as $allowed) {
            if ($origin === trim($allowed)) {
                $isAllowed = true;
                break;
            }
        }

        // Si está permitido, responder con el origen específico (no *)
        if ($isAllowed) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: " . (self::$config['supports_credentials'] ? 'true' : 'false'));
        }

        // Headers de seguridad adicionales
        foreach (self::$config['security_headers'] ?? [] as $header => $value) {
            header("{$header}: {$value}");
        }

        // Métodos permitidos
        $methods = implode(', ', self::$config['allowed_methods'] ?? []);
        header("Access-Control-Allow-Methods: {$methods}");

        // Headers permitidos
        $headers = implode(', ', self::$config['allowed_headers'] ?? []);
        header("Access-Control-Allow-Headers: {$headers}");

        // Headers expuestos
        if (!empty(self::$config['exposed_headers'])) {
            $exposed = implode(', ', self::$config['exposed_headers']);
            header("Access-Control-Expose-Headers: {$exposed}");
        }

        // Cache preflight
        header("Access-Control-Max-Age: " . (self::$config['max_age'] ?? 3600));

        // Responder a preflight (OPTIONS)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
