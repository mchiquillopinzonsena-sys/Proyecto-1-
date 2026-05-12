<?php

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PDO;

/**
 * Lectura de catálogo y simulación de cotización (parametros_cotizador + parametros_equipos).
 */
class CotizadorService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarParametrosActivos(): array
    {
        if ($this->legacyCotizador()) {
            $st = $this->pdo->query(
                'SELECT id_param AS id, CONCAT(tipo_proyecto, "_", nombre_param) AS codigo,
                        nombre_param AS nombre, descripcion, tipo_proyecto AS tipo_parametro,
                        valor AS valor_base, unidad, activo, actualizado_en AS created_at
                 FROM parametros_cotizador WHERE activo = 1 ORDER BY tipo_proyecto ASC, nombre_param ASC'
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        }

        $st = $this->pdo->query(
            'SELECT id, codigo, nombre, descripcion, tipo_parametro, valor_base, unidad, activo, created_at
             FROM parametros_cotizador WHERE activo = 1 ORDER BY codigo ASC'
        );

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarEquiposActivos(): array
    {
        if (!$this->hasTable('parametros_equipos')) {
            $st = $this->pdo->query(
                'SELECT tipo_proyecto, COALESCE(SUM(valor), 0) AS valor_base, COUNT(*) AS total_parametros
                 FROM parametros_cotizador
                 WHERE activo = 1
                 GROUP BY tipo_proyecto
                 ORDER BY tipo_proyecto ASC'
            );

            $rows = [];
            $id = 1;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = [
                    'id' => $id++,
                    'nombre_equipo' => ucfirst((string) $row['tipo_proyecto']),
                    'tipo_equipo' => $row['tipo_proyecto'],
                    'valor_inspeccion_base' => round((float) $row['valor_base'], 2),
                    'tiempo_inspeccion_minutos' => null,
                    'complejidad' => 'base',
                    'activo' => 1,
                    'created_at' => null,
                    'total_parametros' => (int) $row['total_parametros'],
                ];
            }

            return $rows;
        }

        $st = $this->pdo->query(
            'SELECT id, nombre_equipo, tipo_equipo, valor_inspeccion_base, tiempo_inspeccion_minutos, complejidad, activo, created_at
             FROM parametros_equipos WHERE activo = 1 ORDER BY nombre_equipo ASC'
        );

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Simula totales: suma equipos y aplica parámetros activos (porcentaje sobre subtotal equipos, valor fijo suma).
     *
     * @param list<array{equipo_id: int, cantidad?: int|float}> $lineasEquipos
     * @return array<string, mixed>
     */
    public function cotizar(array $lineasEquipos): array
    {
        if ($lineasEquipos === []) {
            throw new ValidationException('Debe enviar al menos una línea en equipos');
        }

        $lineasOut = [];
        $subtotalEquipos = 0.0;

        foreach ($lineasEquipos as $idx => $line) {
            $eqId = (int) ($line['equipo_id'] ?? 0);
            $qty = isset($line['cantidad']) ? (float) $line['cantidad'] : 1.0;
            if ($eqId < 1 || $qty <= 0) {
                throw new ValidationException("Línea {$idx}: equipo_id y cantidad (>0) son obligatorios");
            }
            $eq = $this->obtenerEquipoCotizable($eqId);
            if (!$eq) {
                throw new NotFoundException("Equipo id {$eqId} no encontrado o inactivo");
            }
            $precio = (float) $eq['valor_inspeccion_base'];
            $sub = round($precio * $qty, 2);
            $subtotalEquipos += $sub;
            $lineasOut[] = [
                'equipo_id' => $eqId,
                'nombre_equipo' => $eq['nombre_equipo'],
                'cantidad' => $qty,
                'valor_unitario' => $precio,
                'subtotal_linea' => $sub,
            ];
        }

        $params = $this->listarParametrosActivos();
        $ajustes = [];
        $delta = 0.0;
        foreach ($params as $p) {
            $tipo = strtolower((string) ($p['tipo_parametro'] ?? ''));
            $base = (float) ($p['valor_base'] ?? 0);
            $codigo = (string) ($p['codigo'] ?? '');
            if ($tipo === '' || $base == 0.0) {
                continue;
            }
            if (str_contains($tipo, 'porcentaje') || str_contains($tipo, 'percent')) {
                $monto = round($subtotalEquipos * ($base / 100.0), 2);
                $delta += $monto;
                $ajustes[] = [
                    'codigo' => $codigo,
                    'nombre' => $p['nombre'],
                    'tipo' => 'porcentaje_sobre_equipos',
                    'valor_aplicado' => $monto,
                ];
            } elseif (str_contains($tipo, 'fijo') || str_contains($tipo, 'valor_fijo')) {
                $delta += $base;
                $ajustes[] = [
                    'codigo' => $codigo,
                    'nombre' => $p['nombre'],
                    'tipo' => 'fijo',
                    'valor_aplicado' => round($base, 2),
                ];
            }
        }

        $subtotalConAjustes = round($subtotalEquipos + $delta, 2);
        $iva = round($subtotalConAjustes * 0.19, 2);
        $total = round($subtotalConAjustes + $iva, 2);

        return [
            'subtotal_equipos' => round($subtotalEquipos, 2),
            'ajustes_parametros' => $ajustes,
            'subtotal_con_ajustes' => $subtotalConAjustes,
            'iva_19' => $iva,
            'total' => $total,
            'lineas' => $lineasOut,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function actualizarParametro(int $id, array $data): array
    {
        if ($this->legacyCotizador()) {
            return $this->actualizarParametroLegacy($id, $data);
        }

        $st = $this->pdo->prepare('SELECT id FROM parametros_cotizador WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            throw new NotFoundException('Parámetro no encontrado');
        }
        $sets = [];
        $params = [];
        foreach (['nombre', 'descripcion', 'tipo_parametro', 'unidad'] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = $data[$k] !== null ? trim((string) $data[$k]) : null;
            }
        }
        if (array_key_exists('valor_base', $data)) {
            $sets[] = 'valor_base = ?';
            $params[] = $data['valor_base'];
        }
        if (array_key_exists('activo', $data)) {
            $sets[] = 'activo = ?';
            $params[] = (int) ((bool) $data['activo']);
        }
        if (array_key_exists('codigo', $data)) {
            $sets[] = 'codigo = ?';
            $params[] = trim((string) $data['codigo']);
        }
        if ($sets === []) {
            return $this->obtenerParametroPorId($id);
        }
        $params[] = $id;
        $this->pdo->prepare('UPDATE parametros_cotizador SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        return $this->obtenerParametroPorId($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function actualizarEquipo(int $id, array $data): array
    {
        if (!$this->hasTable('parametros_equipos')) {
            throw new NotFoundException('Catalogo de equipos no disponible en esta base de datos');
        }

        $st = $this->pdo->prepare('SELECT id FROM parametros_equipos WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            throw new NotFoundException('Equipo no encontrado');
        }
        $sets = [];
        $params = [];
        foreach (['nombre_equipo', 'tipo_equipo', 'complejidad'] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = $data[$k] !== null ? trim((string) $data[$k]) : null;
            }
        }
        if (array_key_exists('valor_inspeccion_base', $data)) {
            $sets[] = 'valor_inspeccion_base = ?';
            $params[] = $data['valor_inspeccion_base'];
        }
        if (array_key_exists('tiempo_inspeccion_minutos', $data)) {
            $sets[] = 'tiempo_inspeccion_minutos = ?';
            $params[] = $data['tiempo_inspeccion_minutos'];
        }
        if (array_key_exists('activo', $data)) {
            $sets[] = 'activo = ?';
            $params[] = (int) ((bool) $data['activo']);
        }
        if ($sets === []) {
            return $this->obtenerEquipoPorId($id);
        }
        $params[] = $id;
        $this->pdo->prepare('UPDATE parametros_equipos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        return $this->obtenerEquipoPorId($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerParametroPorId(int $id): array
    {
        if ($this->legacyCotizador()) {
            $st = $this->pdo->prepare(
                'SELECT id_param AS id, CONCAT(tipo_proyecto, "_", nombre_param) AS codigo,
                        nombre_param AS nombre, descripcion, tipo_proyecto AS tipo_parametro,
                        valor AS valor_base, unidad, activo, actualizado_en AS created_at
                 FROM parametros_cotizador WHERE id_param = ? LIMIT 1'
            );
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new NotFoundException('Parametro no encontrado');
            }

            return $row;
        }

        $st = $this->pdo->prepare('SELECT * FROM parametros_cotizador WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Parámetro no encontrado');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerEquipoPorId(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM parametros_equipos WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Equipo no encontrado');
        }

        return $row;
    }

    private function obtenerEquipoCotizable(int $id): ?array
    {
        if ($this->hasTable('parametros_equipos')) {
            $st = $this->pdo->prepare(
                'SELECT id, nombre_equipo, valor_inspeccion_base, tiempo_inspeccion_minutos
                 FROM parametros_equipos WHERE id = ? AND activo = 1 LIMIT 1'
            );
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        $equipos = $this->listarEquiposActivos();
        foreach ($equipos as $equipo) {
            if ((int) $equipo['id'] === $id) {
                return [
                    'id' => $equipo['id'],
                    'nombre_equipo' => $equipo['nombre_equipo'],
                    'valor_inspeccion_base' => $equipo['valor_inspeccion_base'],
                    'tiempo_inspeccion_minutos' => null,
                ];
            }
        }

        return null;
    }

    private function actualizarParametroLegacy(int $id, array $data): array
    {
        $st = $this->pdo->prepare('SELECT id_param FROM parametros_cotizador WHERE id_param = ? LIMIT 1');
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            throw new NotFoundException('Parametro no encontrado');
        }

        $sets = [];
        $params = [];
        if (array_key_exists('nombre', $data)) {
            $sets[] = 'nombre_param = ?';
            $params[] = trim((string) $data['nombre']);
        }
        if (array_key_exists('descripcion', $data)) {
            $sets[] = 'descripcion = ?';
            $params[] = $data['descripcion'] !== null ? trim((string) $data['descripcion']) : null;
        }
        if (array_key_exists('tipo_parametro', $data)) {
            $sets[] = 'tipo_proyecto = ?';
            $params[] = trim((string) $data['tipo_parametro']);
        }
        if (array_key_exists('valor_base', $data)) {
            $sets[] = 'valor = ?';
            $params[] = $data['valor_base'];
        }
        if (array_key_exists('unidad', $data)) {
            $sets[] = 'unidad = ?';
            $params[] = trim((string) $data['unidad']);
        }
        if (array_key_exists('activo', $data)) {
            $sets[] = 'activo = ?';
            $params[] = (int) ((bool) $data['activo']);
        }

        if ($sets !== []) {
            $params[] = $id;
            $this->pdo->prepare('UPDATE parametros_cotizador SET ' . implode(', ', $sets) . ' WHERE id_param = ?')->execute($params);
        }

        return $this->obtenerParametroPorId($id);
    }

    private function legacyCotizador(): bool
    {
        return $this->hasColumn('parametros_cotizador', 'id_param') && !$this->hasColumn('parametros_cotizador', 'id');
    }

    private function hasTable(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return $cache[$table] = (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (\Throwable) {
            return $cache[$table] = false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return $cache[$key] = false;
        }
    }
}
