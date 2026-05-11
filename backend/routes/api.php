<?php

/**
 * Dispatcher API /api/v1 — bootstrap JWT y enrutado.
 * Variables heredadas desde index.php: $method, $path (ruta URI ya sin prefijo /intermica-api si aplica).
 */

use App\Enums\ServiceStatus;
use App\Enums\UserRoles;
use App\Exceptions\AppException;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\JWTHelper;
use App\Helpers\ResponseHelper;
use App\Http\RequestContext;
use App\Middleware\AuthMiddleware;
use App\Middleware\RBACMiddleware;
use App\Services\AuthService;
use App\Services\CuentaCobroService;
use App\Services\StockService;
use Database\Database;

$jwtSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: '';
if ($jwtSecret === '' || $jwtSecret === false) {
    http_response_code(500);
    echo json_encode(ResponseHelper::error('JWT_SECRET no configurado', 500));
    exit;
}
JWTHelper::setSecret($jwtSecret);

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

/** Rutas accesibles sin JWT (rol público). */
$publicRouteKeys = [
    'GET /api/v1/health',
    'POST /api/v1/auth/login',
    'POST /api/v1/auth/refresh',
];

$routeKey = $method . ' ' . ($path === '/' ? '/' : rtrim($path, '/'));
$isPublic = in_array($routeKey, $publicRouteKeys, true);

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function send_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

/**
 * null = sin restricción (admin/técnico); int = cliente_id; -1 = cliente sin perfil (sin filas).
 */
function resolve_cliente_scope(PDO $pdo, RequestContext $ctx): ?int
{
    if ($ctx->role !== UserRoles::CLIENTE) {
        return null;
    }
    $q = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? AND activo = 1 LIMIT 1');
    $q->execute([$ctx->userId]);
    $cid = $q->fetchColumn();

    return $cid ? (int) $cid : -1;
}

try {
    $pdo = Database::getInstance();

    if ($isPublic) {
        if ($routeKey === 'GET /api/v1/health') {
            send_json(ResponseHelper::success([
                'status' => 'ok',
                'service' => 'intermica-api',
            ], 'API operativa'));
            exit;
        }
        if ($routeKey === 'POST /api/v1/auth/login') {
            $body = json_input();
            $email = trim((string) ($body['email'] ?? ''));
            $password = (string) ($body['password'] ?? '');
            if ($email === '' || $password === '') {
                throw new ValidationException('email y password son obligatorios');
            }
            $auth = new AuthService($pdo);
            $tokens = $auth->login($email, $password);
            send_json(ResponseHelper::success($tokens, 'Autenticación exitosa'));
            exit;
        }
        if ($routeKey === 'POST /api/v1/auth/refresh') {
            $body = json_input();
            $rt = (string) ($body['refresh_token'] ?? '');
            if ($rt === '') {
                throw new ValidationException('refresh_token es obligatorio');
            }
            $auth = new AuthService($pdo);
            $tokens = $auth->refresh($rt);
            send_json(ResponseHelper::success($tokens, 'Token renovado'));
            exit;
        }
    }

    $ctx = AuthMiddleware::requireBearerToken();
    (new AuthService($pdo))->assertActiveAccessToken($ctx->token);

    if (!UserRoles::isValid($ctx->role)) {
        throw new ForbiddenException('Rol de usuario no reconocido');
    }

    RBACMiddleware::assertRole($method, $path, $ctx->role);

    if ($routeKey === 'GET /api/v1/servicios') {
        $scope = resolve_cliente_scope($pdo, $ctx);
        $sql = "SELECT s.id, s.numero_servicio, s.cliente_id, s.estado, s.fecha_solicitud, s.fecha_programada,
                       s.valor_estimado, s.valor_final, s.created_at
                FROM servicios s WHERE s.activo = 1";
        $params = [];
        if ($scope !== null) {
            $sql .= ' AND s.cliente_id = ?';
            $params[] = $scope;
        }
        $sql .= ' ORDER BY s.id DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        send_json(ResponseHelper::success($stmt->fetchAll(PDO::FETCH_ASSOC)));
        exit;
    }

    if ($method === 'GET' && preg_match('#^/api/v1/servicios/(\d+)$#', $path, $m)) {
        $id = (int) $m[1];
        $scope = resolve_cliente_scope($pdo, $ctx);
        $sql = 'SELECT s.* FROM servicios s WHERE s.id = ? AND s.activo = 1';
        $params = [$id];
        if ($scope !== null) {
            $sql .= ' AND s.cliente_id = ?';
            $params[] = $scope;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Servicio no encontrado');
        }
        send_json(ResponseHelper::success($row));
        exit;
    }

    if ($method === 'PATCH' && preg_match('#^/api/v1/servicios/(\d+)/estado$#', $path, $m)) {
        $id = (int) $m[1];
        $body = json_input();
        $nuevo = (string) ($body['estado'] ?? '');
        if (!ServiceStatus::isValid($nuevo)) {
            throw new ValidationException('estado no válido', ['estado' => ServiceStatus::ALL]);
        }

        $cuenta = null;
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM servicios WHERE id = ? AND activo = 1 FOR UPDATE'
            );
            $stmt->execute([$id]);
            $svc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$svc) {
                throw new NotFoundException('Servicio no encontrado');
            }
            $scope = resolve_cliente_scope($pdo, $ctx);
            if ($scope !== null && (int) $svc['cliente_id'] !== $scope) {
                throw new ForbiddenException('No puede modificar este servicio');
            }

            $anterior = (string) $svc['estado'];
            if ($anterior === $nuevo) {
                $pdo->commit();
                send_json(ResponseHelper::success(['id' => $id, 'estado' => $nuevo], 'Sin cambios'));
                exit;
            }

            $pdo->prepare('UPDATE servicios SET estado = ? WHERE id = ?')->execute([$nuevo, $id]);

            if ($nuevo === ServiceStatus::COMPLETADO && $anterior !== ServiceStatus::COMPLETADO) {
                $stock = new StockService($pdo);
                $stock->aplicarDescuentoPorServicioCompletado($id, $ctx->userId);
                $cuentaSvc = new CuentaCobroService($pdo);
                $cuenta = $cuentaSvc->generarAutomaticaSiServicioCompletado($id, $ctx->userId);
            }

            $pdo->commit();

            $payload = ['id' => $id, 'estado' => $nuevo];
            if ($cuenta !== null) {
                $payload['cuenta_cobro'] = $cuenta;
            }
            send_json(ResponseHelper::success($payload, 'Estado actualizado'));
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    if ($routeKey === 'POST /api/v1/cuentas') {
        $body = json_input();
        $ids = $body['servicios_ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            throw new ValidationException('servicios_ids debe ser un arreglo no vacío');
        }
        $fv = isset($body['fecha_vencimiento']) ? (string) $body['fecha_vencimiento'] : null;
        $pdo->beginTransaction();
        try {
            $cuentaSvc = new CuentaCobroService($pdo);
            $out = $cuentaSvc->generarDesdeServicios(array_map('intval', $ids), $ctx->userId, $fv);
            $pdo->commit();
            send_json(ResponseHelper::success($out, 'Cuenta de cobro generada: ' . $out['numero']), 201);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

    http_response_code(404);
    send_json(ResponseHelper::error('Ruta no encontrada', 404));
} catch (ValidationException $e) {
    http_response_code($e->getStatusCode());
    send_json(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (AuthException $e) {
    http_response_code(401);
    send_json(ResponseHelper::error($e->getMessage(), 401, $e->getErrors()));
} catch (ForbiddenException $e) {
    http_response_code(403);
    send_json(ResponseHelper::error($e->getMessage(), 403));
} catch (NotFoundException $e) {
    http_response_code(404);
    send_json(ResponseHelper::error($e->getMessage(), 404));
} catch (AppException $e) {
    http_response_code($e->getStatusCode());
    send_json(ResponseHelper::error($e->getMessage(), $e->getStatusCode(), $e->getErrors()));
} catch (Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    send_json(ResponseHelper::error('Error interno del servidor', 500));
}
