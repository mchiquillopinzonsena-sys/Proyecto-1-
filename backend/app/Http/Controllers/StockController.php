<?php

namespace App\Http\Controllers;

use App\Services\StockService;

/**
 * StockController — Endpoints REST para inventario (RN-02)
 *
 * GET  /api/v1/stock           → index()   listar artículos
 * GET  /api/v1/stock/:id       → show()    detalle de artículo
 * POST /api/v1/stock           → store()   crear artículo
 * PATCH /api/v1/stock/:id      → update()  actualizar artículo
 * GET  /api/v1/stock/:id/movimientos → movimientos()  historial de movimientos
 */
class StockController extends BaseController
{
    // ------------------------------------------------------------------
    // GET /api/v1/stock
    // ------------------------------------------------------------------
    public function index(): void
    {
        $this->authorize('stock.leer');

        $stmt = $this->pdo->prepare(
            'SELECT id, codigo_articulo, nombre_articulo, descripcion,
                    cantidad_disponible, cantidad_minima, ubicacion_almacen,
                    precio_unitario, activo, created_at
             FROM stock
             WHERE activo = 1
             ORDER BY nombre_articulo ASC
             LIMIT 500'
        );
        $stmt->execute();
        $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // ------------------------------------------------------------------
    // GET /api/v1/stock/:id
    // ------------------------------------------------------------------
    public function show(int $id): void
    {
        $this->authorize('stock.leer');
        $this->success($this->findOrFail('stock', $id));
    }

    // ------------------------------------------------------------------
    // POST /api/v1/stock
    // ------------------------------------------------------------------
    public function store(): void
    {
        $this->authorize('stock.actualizar');

        $body = $this->getJSON();
        $this->validate($body, [
            'codigo_articulo'  => ['required'],
            'nombre_articulo'  => ['required'],
            'cantidad_disponible' => ['required', 'numeric'],
        ]);

        $codigo   = trim((string) ($body['codigo_articulo'] ?? ''));
        $nombre   = trim((string) ($body['nombre_articulo'] ?? ''));
        $qty      = (int) ($body['cantidad_disponible'] ?? 0);
        $qtyMin   = (int) ($body['cantidad_minima'] ?? 0);
        $precio   = isset($body['precio_unitario']) ? (float) $body['precio_unitario'] : null;
        $ubicacion = isset($body['ubicacion_almacen']) ? trim((string) $body['ubicacion_almacen']) : null;
        $desc     = isset($body['descripcion']) ? trim((string) $body['descripcion']) : null;

        // Verificar código único
        $dup = $this->pdo->prepare('SELECT id FROM stock WHERE codigo_articulo = ? LIMIT 1');
        $dup->execute([$codigo]);
        if ($dup->fetchColumn()) {
            throw new \App\Exceptions\ValidationException('El código de artículo ya existe');
        }

        $this->pdo->prepare(
            'INSERT INTO stock (codigo_articulo, nombre_articulo, descripcion, cantidad_disponible,
                                cantidad_minima, ubicacion_almacen, precio_unitario, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([$codigo, $nombre, $desc, $qty, $qtyMin, $ubicacion, $precio]);

        $newId = (int) $this->pdo->lastInsertId();
        $this->success($this->findOrFail('stock', $newId), 'Artículo creado', 201);
    }

    // ------------------------------------------------------------------
    // PATCH /api/v1/stock/:id
    // ------------------------------------------------------------------
    public function update(int $id): void
    {
        $this->authorize('stock.actualizar');

        $this->findOrFail('stock', $id); // lanza 404 si no existe
        $body = $this->getJSON();

        $sets   = [];
        $params = [];

        foreach (['nombre_articulo', 'descripcion', 'ubicacion_almacen'] as $k) {
            if (array_key_exists($k, $body)) {
                $sets[]   = "{$k} = ?";
                $params[] = $body[$k] !== null ? trim((string) $body[$k]) : null;
            }
        }
        foreach (['cantidad_minima'] as $k) {
            if (array_key_exists($k, $body)) {
                $sets[]   = "{$k} = ?";
                $params[] = (int) $body[$k];
            }
        }
        if (array_key_exists('precio_unitario', $body)) {
            $sets[]   = 'precio_unitario = ?';
            $params[] = $body['precio_unitario'] !== null ? (float) $body['precio_unitario'] : null;
        }
        if (array_key_exists('activo', $body)) {
            $sets[]   = 'activo = ?';
            $params[] = (int) ((bool) $body['activo']);
        }

        if ($sets !== []) {
            $params[] = $id;
            $this->pdo->prepare('UPDATE stock SET ' . implode(', ', $sets) . ' WHERE id = ?')
                      ->execute($params);
        }

        $this->success($this->findOrFail('stock', $id), 'Artículo actualizado');
    }

    // ------------------------------------------------------------------
    // GET /api/v1/stock/:id/movimientos
    // ------------------------------------------------------------------
    public function movimientos(int $id): void
    {
        $this->authorize('stock.leer');

        $this->findOrFail('stock', $id);

        $stmt = $this->pdo->prepare(
            'SELECT ms.id, ms.tipo_movimiento, ms.cantidad,
                    ms.cantidad_anterior, ms.cantidad_nueva, ms.razon,
                    ms.usuario_id, ms.servicio_id, ms.created_at
             FROM movimientos_stock ms
             WHERE ms.stock_id = ?
             ORDER BY ms.created_at DESC
             LIMIT 200'
        );
        $stmt->execute([$id]);
        $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
