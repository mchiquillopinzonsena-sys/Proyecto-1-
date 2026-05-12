<?php
/**
 * Cuentas Cobro Controller - Gestión de cuentas de cobro
 */

namespace App\Http\Controllers;

use App\Services\CuentaCobroService;

class CuentasCobroController extends BaseController
{
    /**
     * POST /api/v1/cuentas
     * Crear nueva cuenta de cobro desde servicios
     */
    public function store(): void
    {
        // Autorización
        $this->authorize('cuentas.crear');

        $body = $this->getJSON();
        $ids = $body['servicios_ids'] ?? null;

        if (!is_array($ids) || $ids === []) {
            throw new \App\Exceptions\ValidationException(
                'servicios_ids debe ser un arreglo no vacío',
                ['servicios_ids' => ['Debe incluir al menos un servicio']]
            );
        }

        $fv = isset($body['fecha_vencimiento']) ? (string) $body['fecha_vencimiento'] : null;

        // Validar fechas
        if ($fv && !strtotime($fv)) {
            throw new \App\Exceptions\ValidationException(
                'fecha_vencimiento inválida',
                ['fecha_vencimiento' => ['Formato de fecha inválido']]
            );
        }

        $this->pdo->beginTransaction();

        try {
            $cuentaSvc = new CuentaCobroService($this->pdo);
            $out = $cuentaSvc->generarDesdeServicios(
                array_map('intval', $ids),
                $this->ctx->userId,
                $fv
            );

            $this->pdo->commit();

            $this->success(
                $out,
                'Cuenta de cobro generada: ' . $out['numero'],
                201
            );
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * GET /api/v1/cuentas/:id
     * Ver detalle de cuenta de cobro
     */
    public function show(int $id): void
    {
        $this->authorize('cuentas.leer');

        $cuenta = $this->findOrFail('cuentas_cobro', $id);

        // Scope: clientes solo ven sus propias cuentas
        $scope = $this->getClientScope();
        if ($scope !== null && (int) $cuenta['cliente_id'] !== $scope) {
            throw new \App\Exceptions\ForbiddenException('No puedes acceder a esta cuenta');
        }

        $this->success($cuenta);
    }

    /**
     * GET /api/v1/cuentas/:id/pdf
     * Descargar PDF de la cuenta
     */
    public function downloadPdf(int $id): void
    {
        $this->authorize('cuentas.leer');

        $cuenta = $this->findOrFail('cuentas_cobro', $id);

        // Scope validation
        $scope = $this->getClientScope();
        if ($scope !== null && (int) $cuenta['cliente_id'] !== $scope) {
            throw new \App\Exceptions\ForbiddenException('No puedes acceder a esta cuenta');
        }

        // TODO: Generar PDF con PDFHelper
        $this->error('PDF generation not yet implemented', 501);
    }

    /**
     * PATCH /api/v1/cuentas/:id/pagar
     * Registrar pago de cuenta
     */
    public function registerPayment(int $id): void
    {
        $this->authorize('cuentas.pagar');

        $body = $this->getJSON();
        $fecha = $body['fecha_pago'] ?? date('Y-m-d');
        $monto = $body['monto'] ?? null;

        $cuenta = $this->findOrFail('cuentas_cobro', $id);

        if (!$monto || (float) $monto <= 0) {
            throw new \App\Exceptions\ValidationException(
                'Monto inválido',
                ['monto' => ['El monto debe ser mayor a 0']]
            );
        }

        $this->pdo->prepare(
            'UPDATE cuentas_cobro SET estado = "pagada", fecha_pago = ? WHERE id = ?'
        )->execute([$fecha, $id]);

        $this->success(['id' => $id, 'estado' => 'pagada'], 'Pago registrado');
    }

    /**
     * Obtiene el cliente_id del usuario si es cliente
     */
    private function getClientScope(): ?int
    {
        $roles = \App\Middleware\RBACMiddleware::getUserRoles($this->pdo, $this->ctx->userId);
        $isCliente = in_array('cliente', array_column($roles, 'nombre'));

        if (!$isCliente) {
            return null;
        }

        $q = $this->pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? AND activo = 1 LIMIT 1');
        $q->execute([$this->ctx->userId]);
        $cid = $q->fetchColumn();

        return $cid ? (int) $cid : -1;
    }
}
