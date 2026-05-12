<?php

/**
 * API Routes Dispatcher - v1
 *
 * Enrutador centralizado que delega a controllers específicos
 * Esto reemplaza la anterior implementación monolítica inline
 */

use App\Exceptions\AppException;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\JWTHelper;
use App\Helpers\ResponseHelper;
use App\Http\RequestContext;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Services\AuthService;
use Database\Database;

// Parse path y method
$path = $path ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = '/' . trim(str_replace('\\', '/', (string) $path), '/');
if ($path !== '/' && str_ends_with($path, '/')) {
    $path = rtrim($path, '/') ?: '/';
}
foreach (['/backend', '/index.php'] as $strip) {
    if (str_starts_with($path, $strip)) {
        $path = '/' . trim(substr($path, strlen($strip)), '/') ?: '/';
    }
}
$method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// Public routes (sin JWT requerido)
$publicRoutes = [
    'GET /api/v1/health',
    'POST /api/v1/auth/login',
    'POST /api/v1/auth/refresh',
];

$routeKey = $method . ' ' . ($path === '/' ? '/' : rtrim($path, '/'));
$isPublic = in_array($routeKey, $publicRoutes, true);

try {
    $pdo = Database::getInstance();

    // ===== PUBLIC ROUTES (Sin autenticación) =====
    if ($isPublic) {
        if ($routeKey === 'GET /api/v1/health') {
            http_response_code(200);
            echo json_encode(ResponseHelper::success([
                'status' => 'ok',
                'service' => 'intermica-api',
                'timestamp' => date('c'),
            ], 'API operativa'));
            exit;
        }

        if ($routeKey === 'POST /api/v1/auth/login') {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            $email = trim((string) ($body['email'] ?? ''));
            $password = (string) ($body['password'] ?? '');

            if ($email === '' || $password === '') {
                throw new ValidationException('email y password son obligatorios');
            }

            $auth = new AuthService($pdo);
            $tokens = $auth->login($email, $password);

            http_response_code(200);
            echo json_encode(ResponseHelper::success($tokens, 'Autenticación exitosa'));
            exit;
        }

        if ($routeKey === 'POST /api/v1/auth/refresh') {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            $rt = (string) ($body['refresh_token'] ?? '');

            if ($rt === '') {
                throw new ValidationException('refresh_token es obligatorio');
            }

            $auth = new AuthService($pdo);
            $tokens = $auth->refresh($rt);

            http_response_code(200);
            echo json_encode(ResponseHelper::success($tokens, 'Token renovado'));
            exit;
        }
    }

    // ===== PROTECTED ROUTES (Requieren JWT) =====
    $ctx = AuthMiddleware::requireBearerToken();
    (new AuthService($pdo))->assertActiveAccessToken($ctx->token);

    // Router de endpoints protegidos
    switch (true) {
        // GET /api/v1/servicios
        case $method === 'GET' && $path === '/api/v1/servicios':
            (new \App\Http\Controllers\ServiciosController($pdo, $ctx))->index();
            exit;

        // GET /api/v1/servicios/:id
        case $method === 'GET' && preg_match('#^/api/v1/servicios/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\ServiciosController($pdo, $ctx))->show((int) $m[1]);
            exit;

        // PATCH /api/v1/servicios/:id/estado
        case $method === 'PATCH' && preg_match('#^/api/v1/servicios/(\d+)/estado$#', $path, $m):
            (new \App\Http\Controllers\ServiciosController($pdo, $ctx))->updateEstado((int) $m[1]);
            exit;

        // POST /api/v1/cuentas
        case $method === 'POST' && $path === '/api/v1/cuentas':
            (new \App\Http\Controllers\CuentasCobroController($pdo, $ctx))->store();
            exit;

        // GET /api/v1/cuentas/:id
        case $method === 'GET' && preg_match('#^/api/v1/cuentas/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\CuentasCobroController($pdo, $ctx))->show((int) $m[1]);
            exit;

        // GET /api/v1/cuentas/:id/pdf
        case $method === 'GET' && preg_match('#^/api/v1/cuentas/(\d+)/pdf$#', $path, $m):
            (new \App\Http\Controllers\CuentasCobroController($pdo, $ctx))->downloadPdf((int) $m[1]);
            exit;

        // PATCH /api/v1/cuentas/:id/pagar
        case $method === 'PATCH' && preg_match('#^/api/v1/cuentas/(\d+)/pagar$#', $path, $m):
            (new \App\Http\Controllers\CuentasCobroController($pdo, $ctx))->registerPayment((int) $m[1]);
            exit;
    }

    // 404 - Ruta no encontrada
    http_response_code(404);
    echo json_encode(ResponseHelper::error('Ruta no encontrada', 404));

} catch (ValidationException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (AuthException $e) {
    http_response_code(401);
    echo json_encode(ResponseHelper::error($e->getMessage(), 401));
} catch (ForbiddenException $e) {
    http_response_code(403);
    echo json_encode(ResponseHelper::error($e->getMessage(), 403));
} catch (NotFoundException $e) {
    http_response_code(404);
    echo json_encode(ResponseHelper::error($e->getMessage(), 404));
} catch (AppException $e) {
    http_response_code($e->getStatusCode());
    echo json_encode(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(ResponseHelper::error('Error interno del servidor', 500));
}

