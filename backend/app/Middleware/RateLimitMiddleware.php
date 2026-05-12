<?php
/**
 * Rate Limit Middleware - Prevent brute force and DDoS attacks
 *
 * Implements sliding window rate limiting with Redis fallback to file-based storage
 * Separate limits for login and general API calls
 */

namespace App\Middleware;

use App\Exceptions\AppException;

class RateLimitMiddleware
{
    private static bool $enabled = true;
    private static int $requests = 100;
    private static int $window = 60;
    private static int $loginRequests = 5;
    private static int $loginWindow = 900;
    private static string $storePath = __DIR__ . '/../../storage/ratelimit';

    public static function init(array $config = []): void
    {
        self::$enabled = $config['enabled'] ?? (bool)(getenv('RATE_LIMIT_ENABLED') ?? true);
        self::$requests = (int)(getenv('RATE_LIMIT_REQUESTS') ?? $config['requests'] ?? 100);
        self::$window = (int)(getenv('RATE_LIMIT_WINDOW_SECONDS') ?? $config['window'] ?? 60);
        self::$loginRequests = (int)(getenv('RATE_LIMIT_LOGIN_REQUESTS') ?? $config['login_requests'] ?? 5);
        self::$loginWindow = (int)(getenv('RATE_LIMIT_LOGIN_WINDOW_SECONDS') ?? $config['login_window'] ?? 900);
        self::$storePath = $config['storage_path'] ?? self::$storePath;

        @mkdir(self::$storePath, 0755, true);
    }

    /**
     * Verifica rate limit para la request actual
     * @throws AppException si se ha excedido el límite
     */
    public static function check(): void
    {
        if (!self::$enabled) {
            return;
        }

        $ip = self::getClientIP();
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // Rate limit especial para login
        if (str_contains($path, '/auth/login')) {
            self::checkLimit($ip, 'login', self::$loginRequests, self::$loginWindow);
        } else {
            self::checkLimit($ip, 'api', self::$requests, self::$window);
        }
    }

    /**
     * Verifica si se ha excedido el límite para un cliente/tipo
     */
    private static function checkLimit(string $ip, string $type, int $maxRequests, int $window): void
    {
        $key = md5("{$type}:{$ip}");
        $file = self::$storePath . "/{$key}.json";

        $data = self::readLimit($file);
        $now = time();

        // Limpiar registros antiguos
        $data = array_filter($data, fn($t) => ($now - $t) < $window);

        // Incrementar contador
        $data[] = $now;

        if (count($data) > $maxRequests) {
            self::writeLimit($file, $data);
            throw new AppException(
                "Demasiadas solicitudes. Intenta de nuevo en " . ceil($window / 60) . " minutos",
                429
            );
        }

        self::writeLimit($file, $data);
    }

    /**
     * Lee datos de límite de archivo
     */
    private static function readLimit(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        $data = $content ? json_decode($content, true) : [];

        return is_array($data) ? $data : [];
    }

    /**
     * Escribe datos de límite a archivo
     */
    private static function writeLimit(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    /**
     * Obtiene IP del cliente (respeta proxies)
     */
    private static function getClientIP(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
