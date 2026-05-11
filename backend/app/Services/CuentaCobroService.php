<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Exceptions\ValidationException;
use PDO;

/**
 * RN-06: generación de cuenta de cobro (número CC-YYYY-XXXX vía trigger + secuencias_documento).
 */
class CuentaCobroService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Crea cuenta e ítems para uno o más servicios del mismo cliente. Transacción externa.
     *
     * @param list<int> $serviciosIds
     * @return array{id: int, numero: string, total: string}
     * @throws ValidationException
     */
    public function generarDesdeServicios(array $serviciosIds, int $usuarioCreadorId, ?string $fechaVencimiento = null): array
    {
        $serviciosIds = array_values(array_unique(array_map('intval', $serviciosIds)));
        if ($serviciosIds === []) {
            throw new ValidationException('Debe indicar al menos un servicio');
        }

        $in = implode(',', array_fill(0, count($serviciosIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, cliente_id, estado, valor_final, numero_servicio
             FROM servicios WHERE id IN ($in) AND activo = 1"
        );
        $stmt->execute($serviciosIds);
        $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($servicios) !== count($serviciosIds)) {
            throw new ValidationException('Uno o más servicios no existen');
        }

        $dup = $this->pdo->prepare("SELECT COUNT(*) FROM cuentas_items WHERE servicio_id IN ($in)");
        $dup->execute($serviciosIds);
        if ((int) $dup->fetchColumn() > 0) {
            throw new ValidationException('Uno o más servicios ya están vinculados a una cuenta de cobro');
        }

        $clienteId = (int) $servicios[0]['cliente_id'];
        foreach ($servicios as $s) {
            if ((int) $s['cliente_id'] !== $clienteId) {
                throw new ValidationException('Todos los servicios deben pertenecer al mismo cliente');
            }
            if (($s['estado'] ?? '') !== ServiceStatus::COMPLETADO) {
                throw new ValidationException('Solo servicios en estado completado pueden facturarse');
            }
        }

        $fv = $fechaVencimiento ?? date('Y-m-d', strtotime('+30 days'));
        $placeholder = 'TMP-' . bin2hex(random_bytes(8));

        $this->pdo->prepare(
            'INSERT INTO cuentas_cobro (numero, cliente_id, fecha_emision, fecha_vencimiento, estado, subtotal, impuesto_iva, total, usuario_creador_id, activo)
             VALUES (?, ?, CURDATE(), ?, ?, 0, 0, 0, ?, 1)'
        )->execute([$placeholder, $clienteId, $fv, 'pendiente', $usuarioCreadorId]);

        $cuentaId = (int) $this->pdo->lastInsertId();

        $stmtNum = $this->pdo->prepare('SELECT numero FROM cuentas_cobro WHERE id = ?');
        $stmtNum->execute([$cuentaId]);
        $numero = (string) $stmtNum->fetchColumn();

        $numeroItem = 1;
        $subtotal = 0.0;
        foreach ($servicios as $s) {
            $monto = $s['valor_final'] !== null ? (float) $s['valor_final'] : $this->sumarItemsServicio((int) $s['id']);
            if ($monto <= 0) {
                $monto = $this->sumarItemsServicio((int) $s['id']);
            }
            $subtotal += $monto;
            $desc = 'Servicio ' . ($s['numero_servicio'] ?? '#' . $s['id']);
            $this->pdo->prepare(
                'INSERT INTO cuentas_items (cuenta_cobro_id, numero_item, servicio_id, descripcion, cantidad, unidad, valor_unitario, impuesto, total)
                 VALUES (?, ?, ?, ?, 1, ?, ?, 0, ?)'
            )->execute([
                $cuentaId,
                $numeroItem++,
                (int) $s['id'],
                $desc,
                'und',
                $monto,
                $monto,
            ]);
        }

        $iva = round($subtotal * 0.19, 2);
        $total = round($subtotal + $iva, 2);
        $this->pdo->prepare(
            'UPDATE cuentas_cobro SET subtotal = ?, impuesto_iva = ?, total = ? WHERE id = ?'
        )->execute([$subtotal, $iva, $total, $cuentaId]);

        $this->pdo->prepare("UPDATE servicios SET cuenta_cobro_generada = 1 WHERE id IN ($in)")->execute($serviciosIds);

        return ['id' => $cuentaId, 'numero' => $numero, 'total' => (string) $total];
    }

    private function sumarItemsServicio(int $servicioId): float
    {
        $q = $this->pdo->prepare('SELECT COALESCE(SUM(valor_total), 0) FROM servicios_items WHERE servicio_id = ?');
        $q->execute([$servicioId]);
        return (float) $q->fetchColumn();
    }

    /**
     * Genera una cuenta para un único servicio recién completado (idempotente por servicio.cuenta_cobro_generada).
     */
    public function generarAutomaticaSiServicioCompletado(int $servicioId, int $usuarioCreadorId): ?array
    {
        $q = $this->pdo->prepare(
            'SELECT id, estado, cuenta_cobro_generada FROM servicios WHERE id = ? AND activo = 1 FOR UPDATE'
        );
        $q->execute([$servicioId]);
        $s = $q->fetch(PDO::FETCH_ASSOC);
        if (!$s || $s['estado'] !== ServiceStatus::COMPLETADO) {
            return null;
        }
        if ((int) $s['cuenta_cobro_generada'] === 1) {
            return null;
        }

        return $this->generarDesdeServicios([$servicioId], $usuarioCreadorId);
    }
}
