<?php
/**
 * Response Helper - Standardized JSON Responses
 */

namespace App\Helpers;

class ResponseHelper
{
    public static function success(mixed $data, string $message = 'Operación exitosa', int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'timestamp' => date('c'),
            'message' => $message,
            'data' => $data,
            'meta' => [
                'version_api' => getenv('APP_VERSION', '1.0.0'),
                'tiempo_respuesta_ms' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000, 2),
            ]
        ];
    }
    
    public static function error(string $message, int $status = 500, array $errors = []): array
    {
        $response = [
            'success' => false,
            'status' => $status,
            'timestamp' => date('c'),
            'message' => $message,
            'meta' => [
                'version_api' => getenv('APP_VERSION', '1.0.0'),
            ]
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $response;
    }
    
    public static function paginated(array $data, int $page, int $perPage, int $total, string $message = 'Datos recuperados', int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'timestamp' => date('c'),
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
            ],
            'meta' => [
                'version_api' => getenv('APP_VERSION', '1.0.0'),
            ]
        ];
    }
}
