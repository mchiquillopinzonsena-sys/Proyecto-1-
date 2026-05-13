<?php
namespace App\Http\Controllers;

use App\Exceptions\ValidationException;

class AgendaController extends BaseController
{
    public function index(): void
    {
        // En listar, podríamos ver los bloqueos según el rol
        $sql = 'SELECT b.*, t.numero_empleado FROM bloqueos_agenda b JOIN tecnicos t ON b.tecnico_id = t.id WHERE b.activo = 1 ORDER BY b.fecha_inicio DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function store(): void
    {
        // Solo un técnico o un admin pueden crear bloqueos
        $roles = \App\Middleware\RBACMiddleware::getUserRoles($this->pdo, $this->ctx->userId);
        $roleNames = array_column($roles, 'nombre');
        
        $body = $this->getJSON();
        
        $tecnico_id = $body['tecnico_id'] ?? null;
        if (in_array('tecnico', $roleNames, true)) {
            // Un técnico solo puede bloquear su propia agenda
            $stmt = $this->pdo->prepare('SELECT id FROM tecnicos WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([$this->ctx->userId]);
            $tecnico_id = $stmt->fetchColumn();
            if (!$tecnico_id) {
                throw new ValidationException('No se encontró su perfil técnico asociado.');
            }
        }

        if (!$tecnico_id) {
            throw new ValidationException('El técnico es obligatorio', ['tecnico_id' => ['Obligatorio']]);
        }

        $fi = $body['fecha_inicio'] ?? '';
        $ff = $body['fecha_fin'] ?? '';
        $tipo = $body['tipo_bloqueo'] ?? 'no_disponible';
        $razon = $body['razon'] ?? '';

        if (!$fi || !$ff) {
            throw new ValidationException('Fechas requeridas');
        }

        $stmt = $this->pdo->prepare('INSERT INTO bloqueos_agenda (tecnico_id, fecha_inicio, fecha_fin, tipo_bloqueo, razon) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tecnico_id, $fi, $ff, $tipo, $razon]);

        $this->success(['id' => $this->pdo->lastInsertId()], 'Bloqueo registrado correctamente', 201);
    }
}
