<?php
/**
 * Servicios Controller - Gestión de servicios termográficos
 */

namespace App\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Services\CuentaCobroService;
use App\Services\StockService;

class ServiciosController extends BaseController
{
    /**
     * GET /api/v1/servicios
     * Listar servicios con scope por rol
     */
    public function index(): void
    {
        // Autorización
        $this->authorize('servicios.leer');

        // Scope: clientes solo ven sus servicios
        $scope = $this->getClientScope();

        $sql = "SELECT s.id, s.numero_servicio, s.cliente_id, s.estado, s.fecha_solicitud,
                       s.fecha_programada, s.valor_estimado, s.valor_final, s.created_at
                FROM servicios s WHERE s.activo = 1";
        $params = [];

        if ($scope !== null) {
            $sql .= ' AND s.cliente_id = ?';
            $params[] = $scope;
        }

        $sql .= ' ORDER BY s.id DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * GET /api/v1/servicios/:id
     * Ver detalle de un servicio
     */
    public function show(int $id): void
    {
        $this->authorize('servicios.leer');

        $scope = $this->getClientScope();
        $sql = 'SELECT s.* FROM servicios s WHERE s.id = ? AND s.activo = 1';
        $params = [$id];

        if ($scope !== null) {
            $sql .= ' AND s.cliente_id = ?';
            $params[] = $scope;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \App\Exceptions\NotFoundException('Servicio no encontrado');
        }

        $this->success($row);
    }

    /**
     * PATCH /api/v1/servicios/:id/estado
     * Cambiar estado del servicio
     */
    public function updateEstado(int $id): void
    {
        $this->authorize('servicios.cambiar_estado');

        $body = $this->getJSON();
        $nuevo = (string) ($body['estado'] ?? '');

        if (!ServiceStatus::isValid($nuevo)) {
            throw new \App\Exceptions\ValidationException('Estado no válido');
        }

        $cuenta = null;
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM servicios WHERE id = ? AND activo = 1 FOR UPDATE');
            $stmt->execute([$id]);
            $svc = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$svc) {
                throw new \App\Exceptions\NotFoundException('Servicio no encontrado');
            }

            $scope = $this->getClientScope();
            if ($scope !== null && (int) $svc['cliente_id'] !== $scope) {
                throw new \App\Exceptions\ForbiddenException('No puede modificar este servicio');
            }

            $anterior = (string) $svc['estado'];
            if ($anterior !== $nuevo) {
                $this->pdo->prepare('UPDATE servicios SET estado = ? WHERE id = ?')
                    ->execute([$nuevo, $id]);

                // Automatizaciones al completar servicio
                if ($nuevo === ServiceStatus::COMPLETADO && $anterior !== ServiceStatus::COMPLETADO) {
                    $stock = new StockService($this->pdo);
                    $stock->aplicarDescuentoPorServicioCompletado($id, $this->ctx->userId);

                    $cuentaSvc = new CuentaCobroService($this->pdo);
                    $cuenta = $cuentaSvc->generarAutomaticaSiServicioCompletado($id, $this->ctx->userId);
                }
            }

            $this->pdo->commit();

            $payload = ['id' => $id, 'estado' => $nuevo];
            if ($cuenta !== null) {
                $payload['cuenta_cobro'] = $cuenta;
            }

            $this->success($payload, 'Estado actualizado');
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene el cliente_id del usuario si es cliente, null si es admin/técnico
     */
    private function getClientScope(): ?int
    {
        // Si no es cliente, sin scope (admin/técnico ven todo)
        $roles = \App\Middleware\RBACMiddleware::getUserRoles($this->pdo, $this->ctx->userId);
        $isCliente = collect($roles)->pluck('nombre')->contains('cliente');

        if (!$isCliente) {
            return null;
        }

        $q = $this->pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? AND activo = 1 LIMIT 1');
        $q->execute([$this->ctx->userId]);
        $cid = $q->fetchColumn();

        return $cid ? (int) $cid : -1;
    }
}

// Helper: función collect() para arrays
if (!function_exists('collect')) {
    function collect(array $items) {
        return new class($items) {
            private array $items;
            public function __construct(array $items) { $this->items = $items; }
            public function pluck($key) { return array_column($this->items, $key); }
            public function contains($value) { return in_array($value, $this->items); }
        };
    }
}
