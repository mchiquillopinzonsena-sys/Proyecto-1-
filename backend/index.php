<?php
/**
 * Intérmica S.A.S - API REST
 * Entry Point with Secure CORS, Rate Limiting, and Security Headers
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Import core classes
use App\Helpers\ResponseHelper;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Middleware\CORSMiddleware;
use App\Middleware\RateLimitMiddleware;

try {
    // 1. Apply CORS Middleware (secure, whitelist-based)
    $corsConfig = require_once __DIR__ . '/config/cors.php';
    CORSMiddleware::init($corsConfig);
    CORSMiddleware::handle();

    // 2. Apply Rate Limiting Middleware
    RateLimitMiddleware::check();

    // Initialize router
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = str_replace('/intermica-api', '', $path);

    // Route dispatcher
    require_once __DIR__ . '/routes/api.php';

} catch (ValidationException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (AppException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(ResponseHelper::error('Error interno del servidor', 500));
    error_log($e->getMessage());
}
