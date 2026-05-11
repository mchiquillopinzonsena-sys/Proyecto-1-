<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Exceptions\ValidationException;
use PDO;

/**
 * RN-02: descuento de inventario al completar servicio (idempotente vía servicios.stock_descuento_aplicado).
 */
class StockService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Debe ejecutarse dentro de una transacción activa.
     *
     * @throws ValidationException
     */
    public function aplicarDescuentoPorServicioCompletado(int $servicioId, int $usuarioId): void
    {
        $q = $this->pdo->prepare(
            'SELECT id, estado, stock_descuento_aplicado FROM servicios WHERE id = ? AND activo = 1 FOR UPDATE'
        );
        $q->execute([$servicioId]);
        $servicio = $q->fetch(PDO::FETCH_ASSOC);
        if (!$servicio) {
            throw new ValidationException('Servicio no encontrado');
        }
        if ($servicio['estado'] !== ServiceStatus::COMPLETADO) {
            return;
        }
        if ((int) $servicio['stock_descuento_aplicado'] === 1) {
            return;
        }

        $items = $this->pdo->prepare(
            'SELECT id, stock_id, cantidad FROM servicios_items WHERE servicio_id = ? AND stock_id IS NOT NULL'
        );
        $items->execute([$servicioId]);
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $stockId = (int) $row['stock_id'];
            $cantidad = (float) $row['cantidad'];
            $consumo = (int) max(1, (int) ceil($cantidad));

            $st = $this->pdo->prepare(
                'SELECT id, cantidad_disponible FROM stock WHERE id = ? AND activo = 1 FOR UPDATE'
            );
            $st->execute([$stockId]);
            $stock = $st->fetch(PDO::FETCH_ASSOC);
            if (!$stock) {
                throw new ValidationException("Stock id {$stockId} no encontrado");
            }
            $antes = (int) $stock['cantidad_disponible'];
            if ($antes < $consumo) {
                throw new ValidationException(
                    "Stock insuficiente para el artículo {$stockId}: disponible {$antes}, requerido {$consumo}"
                );
            }
            $despues = $antes - $consumo;

            $this->pdo->prepare('UPDATE stock SET cantidad_disponible = ? WHERE id = ?')->execute([$despues, $stockId]);

            $ins = $this->pdo->prepare(
                'INSERT INTO movimientos_stock (stock_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, razon, usuario_id, servicio_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $stockId,
                'salida',
                $consumo,
                $antes,
                $despues,
                'RN-02 Servicio completado #' . $servicioId,
                $usuarioId,
                $servicioId,
            ]);
        }

        $this->pdo->prepare(
            'UPDATE servicios SET stock_descuento_aplicado = 1 WHERE id = ?'
        )->execute([$servicioId]);
    }
}
