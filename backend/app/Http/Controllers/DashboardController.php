<?php

namespace App\Http\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        // En el futuro, roles como técnico o cliente tendrán métricas filtradas.
        // Por ahora, traemos una vista global útil para el Admin y general.
        
        // 1. Conteo general de servicios por estado
        $stmtSvc = $this->pdo->prepare('
            SELECT estado, COUNT(*) as cantidad 
            FROM servicios 
            WHERE activo = 1 
            GROUP BY estado
        ');
        $stmtSvc->execute();
        $serviciosStats = $stmtSvc->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        $totalServicios = array_sum($serviciosStats);

        // 2. Conteo de cuentas de cobro vencidas
        $stmtCc = $this->pdo->prepare('
            SELECT COUNT(*) FROM cuentas_cobro 
            WHERE estado IN ("pendiente", "parcial", "vencida") 
            AND fecha_vencimiento < CURDATE() 
            AND activo = 1
        ');
        $stmtCc->execute();
        $cuentasVencidas = (int) $stmtCc->fetchColumn();

        // 3. Conteo de stock bajo mínimo
        $stmtStock = $this->pdo->prepare('
            SELECT COUNT(*) FROM stock 
            WHERE cantidad_disponible < cantidad_minima 
            AND activo = 1
        ');
        $stmtStock->execute();
        $stockAlertas = (int) $stmtStock->fetchColumn();

        $this->success([
            'servicios' => [
                'total' => $totalServicios,
                'desglose' => $serviciosStats
            ],
            'cuentas_vencidas' => $cuentasVencidas,
            'alertas_stock' => $stockAlertas
        ], 'Métricas del Dashboard');
    }
}
