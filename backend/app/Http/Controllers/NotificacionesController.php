<?php
namespace App\Http\Controllers;

use App\Exceptions\ValidationException;

class NotificacionesController extends BaseController
{
    public function index(): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$this->ctx->userId]);
        $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function markAsRead(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $this->ctx->userId]);
        $this->success(null, 'Notificación marcada como leída');
    }
}
