<?php
/**
 * Log Helper - Centralized logging system
 */

namespace App\Helpers;

class LogHelper
{
    private static string $logDir = __DIR__ . '/../../logs';
    
    public static function log(string $message, string $level = 'info', string $file = 'app.log'): void
    {
        self::ensureLogDir();
        
        $logFile = self::$logDir . '/' . $file;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }
    
    public static function auditLog(int $userId, string $action, string $details, ?string $stateFrom = null, ?string $stateTo = null): void
    {
        $message = "User: {$userId} | Action: {$action} | Details: {$details}";
        if ($stateFrom && $stateTo) {
            $message .= " | State: {$stateFrom} -> {$stateTo}";
        }
        
        self::log($message, 'audit', 'auditoria.log');
    }
    
    public static function errorLog(string $message, ?string $trace = null): void
    {
        $fullMessage = $message;
        if ($trace) {
            $fullMessage .= " | Trace: {$trace}";
        }
        
        self::log($fullMessage, 'error', 'errores.log');
    }
    
    private static function ensureLogDir(): void
    {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
}
