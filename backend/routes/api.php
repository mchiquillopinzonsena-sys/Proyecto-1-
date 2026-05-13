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
use App\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Database\Database;

$jwtSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';
if ($jwtSecret === '' || $jwtSecret === false) {
    http_response_code(500);
    echo json_encode(ResponseHelper::error('JWT_SECRET no configurado', 500));
    exit;
}
JWTHelper::setSecret($jwtSecret);

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
    'GET /api/v1/setup',
];

$routeKey = $method . ' ' . ($path === '/' ? '/' : rtrim($path, '/'));
$isPublic = in_array($routeKey, $publicRoutes, true);

try {
    $pdo = Database::getInstance();

    // ===== PUBLIC ROUTES (Sin autenticación) =====
    if ($isPublic) {
        if ($routeKey === 'GET /api/v1/setup') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
            if ($stmt->fetchColumn() == 0) {
                $hash = password_hash('admin123', PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO usuarios (email, nombre_completo, password_hash, rol) VALUES ('admin@intermica.com.co', 'Administrador Principal', ?, 'admin')")->execute([$hash]);
                
                $userId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'admin'")->execute([$userId]);
                
                http_response_code(200);
                echo json_encode(ResponseHelper::success(['email' => 'admin@intermica.com.co', 'password' => 'admin123'], 'Usuario Admin autogenerado.'));
            } else {
                http_response_code(400);
                echo json_encode(ResponseHelper::error('Ya existen usuarios. Setup deshabilitado.', 400));
            }
            exit;
        }

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
        // GET /api/v1/usuarios/me
        case $method === 'GET' && $path === '/api/v1/usuarios/me':
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->me();
            exit;

        // GET /api/v1/usuarios
        case $method === 'GET' && $path === '/api/v1/usuarios':
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->index();
            exit;

        // GET /api/v1/usuarios/:id
        case $method === 'GET' && preg_match('#^/api/v1/usuarios/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->show((int) $m[1]);
            exit;

        // POST /api/v1/usuarios
        case $method === 'POST' && $path === '/api/v1/usuarios':
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->store();
            exit;

        // PATCH /api/v1/usuarios/:id
        case $method === 'PATCH' && preg_match('#^/api/v1/usuarios/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->update((int) $m[1]);
            exit;

        // DELETE /api/v1/usuarios/:id
        case $method === 'DELETE' && preg_match('#^/api/v1/usuarios/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\UsuariosController($pdo, $ctx))->destroy((int) $m[1]);
            exit;

        // GET /api/v1/cotizador/parametros
        case $method === 'GET' && $path === '/api/v1/cotizador/parametros':
            (new \App\Http\Controllers\CotizadorController($pdo, $ctx))->parametros();
            exit;

        // GET /api/v1/cotizador/equipos
        case $method === 'GET' && $path === '/api/v1/cotizador/equipos':
            (new \App\Http\Controllers\CotizadorController($pdo, $ctx))->equipos();
            exit;

        // POST /api/v1/cotizador/cotizar
        case $method === 'POST' && $path === '/api/v1/cotizador/cotizar':
            (new \App\Http\Controllers\CotizadorController($pdo, $ctx))->cotizar();
            exit;

        // PATCH /api/v1/cotizador/parametros/:id
        case $method === 'PATCH' && preg_match('#^/api/v1/cotizador/parametros/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\CotizadorController($pdo, $ctx))->updateParametro((int) $m[1]);
            exit;

        // PATCH /api/v1/cotizador/equipos/:id
        case $method === 'PATCH' && preg_match('#^/api/v1/cotizador/equipos/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\CotizadorController($pdo, $ctx))->updateEquipo((int) $m[1]);
            exit;

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

        // POST /api/v1/servicios
        case $method === 'POST' && $path === '/api/v1/servicios':
            (new \App\Http\Controllers\ServiciosController($pdo, $ctx))->store();
            exit;

        // GET /api/v1/agenda
        case $method === 'GET' && $path === '/api/v1/agenda':
            (new \App\Http\Controllers\AgendaController($pdo, $ctx))->index();
            exit;
        // POST /api/v1/agenda
        case $method === 'POST' && $path === '/api/v1/agenda':
            (new \App\Http\Controllers\AgendaController($pdo, $ctx))->store();
            exit;

        // GET /api/v1/notificaciones
        case $method === 'GET' && $path === '/api/v1/notificaciones':
            (new \App\Http\Controllers\NotificacionesController($pdo, $ctx))->index();
            exit;
        // PATCH /api/v1/notificaciones/:id/leer
        case $method === 'PATCH' && preg_match('#^/api/v1/notificaciones/(\d+)/leer$#', $path, $m):
            (new \App\Http\Controllers\NotificacionesController($pdo, $ctx))->markAsRead((int) $m[1]);
            exit;

        // POST /api/v1/auth/logout
        case $method === 'POST' && $path === '/api/v1/auth/logout':
            (new \App\Services\AuthService($pdo))->revokeByAccessToken($ctx->token);
            http_response_code(200);
            echo json_encode(\App\Helpers\ResponseHelper::success(null, 'Sesión cerrada'));
            exit;

        // GET /api/v1/dashboard
        case $method === 'GET' && $path === '/api/v1/dashboard':
            (new \App\Http\Controllers\DashboardController($pdo, $ctx))->index();
            exit;

        // GET /api/v1/cuentas
        case $method === 'GET' && $path === '/api/v1/cuentas':
            (new \App\Http\Controllers\CuentasCobroController($pdo, $ctx))->index();
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

        // GET /api/v1/stock
        case $method === 'GET' && $path === '/api/v1/stock':
            (new \App\Http\Controllers\StockController($pdo, $ctx))->index();
            exit;

        // GET /api/v1/stock/:id
        case $method === 'GET' && preg_match('#^/api/v1/stock/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\StockController($pdo, $ctx))->show((int) $m[1]);
            exit;

        // POST /api/v1/stock
        case $method === 'POST' && $path === '/api/v1/stock':
            (new \App\Http\Controllers\StockController($pdo, $ctx))->store();
            exit;

        // PATCH /api/v1/stock/:id
        case $method === 'PATCH' && preg_match('#^/api/v1/stock/(\d+)$#', $path, $m):
            (new \App\Http\Controllers\StockController($pdo, $ctx))->update((int) $m[1]);
            exit;

        // GET /api/v1/stock/:id/movimientos
        case $method === 'GET' && preg_match('#^/api/v1/stock/(\d+)/movimientos$#', $path, $m):
            (new \App\Http\Controllers\StockController($pdo, $ctx))->movimientos((int) $m[1]);
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
} catch (\PDOException $e) {
    // Capturar Errores de Triggers MySQL (SIGNAL SQLSTATE '45000')
    if ($e->getCode() === '45000') {
        http_response_code(400);
        $msg = $e->getMessage();
        // Limpiar el prefijo técnico (ej: "SQLSTATE[45000]: <<Unknown error>>: 1644 El técnico está ocupado")
        if (preg_match('/1644\s+(.*)/', $msg, $m)) {
            $msg = $m[1];
        }
        echo json_encode(ResponseHelper::error($msg, 400));
    } else {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(ResponseHelper::error('Error interno de base de datos', 500));
    }
} catch (Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(ResponseHelper::error('Error interno del servidor', 500));
}

