<?php
/**
 * Logger Service - Centralizado logging con Monolog
 *
 * Maneja:
 * - Logs de aplicación
 * - Logs de auditoría (acciones de usuarios)
 * - Logs de errores
 * - Logs de seguridad
 */

namespace App\Services;

use Monolog\Logger;
use Monolog\Handlers\StreamHandler;
use Monolog\Handlers\RotatingFileHandler;
use Monolog\Processors\UidProcessor;
use Monolog\Processors\IntrospectionProcessor;
use Monolog\Processors\PsrLogMessageProcessor;
use Monolog\Formatter\JsonFormatter;

class LoggerService
{
    private static array $loggers = [];
    private static string $logPath = __DIR__ . '/../../logs';

    /**
     * Inicializa los loggers
     */
    public static function init(string $logPath = ''): void
    {
        if ($logPath) {
            self::$logPath = $logPath;
        }
        @mkdir(self::$logPath, 0755, true);
    }

    /**
     * Obtiene o crea un logger
     */
    private static function getLogger(string $channel): Logger
    {
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logger = new Logger($channel);

        // Rotating file handler (max 10 files de 10MB cada uno)
        $handler = new RotatingFileHandler(
            self::$logPath . "/{$channel}.log",
            10,
            Logger::DEBUG
        );
        $handler->setFormatter(new JsonFormatter());

        // Processors para metadata
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $logger->pushHandler($handler);

        self::$loggers[$channel] = $logger;
        return $logger;
    }

    /**
     * Log de auditoría - acciones de usuarios
     */
    public static function audit(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        array $changes = [],
        string $ip = ''
    ): void {
        $logger = self::getLogger('auditoria');
        $logger->info('User action', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => $ip ?: ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Log de error
     */
    public static function error(string $message, array $context = [], \Throwable $exception = null): void
    {
        $logger = self::getLogger('errores');
        if ($exception) {
            $logger->error($message, array_merge($context, [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]));
        } else {
            $logger->error($message, $context);
        }
    }

    /**
     * Log de seguridad - intentos de acceso no autorizado, etc.
     */
    public static function security(string $event, array $context = []): void
    {
        $logger = self::getLogger('seguridad');
        $logger->warning('Security event', array_merge($context, [
            'event' => $event,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('c'),
        ]));
    }

    /**
     * Log de aplicación general
     */
    public static function info(string $message, array $context = []): void
    {
        $logger = self::getLogger('app');
        $logger->info($message, $context);
    }

    /**
     * Log de debug (solo development)
     */
    public static function debug(string $message, array $context = []): void
    {
        if ((getenv('APP_ENV') ?? 'development') === 'development') {
            $logger = self::getLogger('debug');
            $logger->debug($message, $context);
        }
    }

    /**
     * Log de acceso a API
     */
    public static function apiAccess(
        string $method,
        string $path,
        int $statusCode,
        ?int $userId = null,
        float $duration = 0
    ): void {
        $logger = self::getLogger('api');
        $logger->info('API Request', [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'user_id' => $userId,
            'duration_ms' => $duration,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('c'),
        ]);
    }
}
