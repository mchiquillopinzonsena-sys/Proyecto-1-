<?php

namespace App\Services;

use App\Enums\UserRoles;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PDO;

class UsuarioService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(?string $rol, ?int $activo, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $off = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($rol !== null && $rol !== '' && UserRoles::isValid($rol)) {
            $where[] = 'rol = ?';
            $params[] = $rol;
        }
        if ($activo !== null) {
            $where[] = 'activo = ?';
            $params[] = $activo ? 1 : 0;
        }
        $w = implode(' AND ', $where);

        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE $w");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $params[] = $perPage;
        $params[] = $off;
        $sql = "SELECT id, email, nombre_completo, rol, telefono, direccion, activo, fecha_creacion, created_at
                FROM usuarios WHERE $w ORDER BY id DESC LIMIT ? OFFSET ?";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $page, $perPage];
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerPorId(int $id): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, email, nombre_completo, rol, telefono, direccion, activo, fecha_creacion, created_at
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Usuario no encontrado');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function crear(array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $nombre = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? ''));
        $rol = (string) ($data['rol'] ?? UserRoles::CLIENTE);
        $telefono = isset($data['telefono']) ? trim((string) $data['telefono']) : null;
        $direccion = isset($data['direccion']) ? trim((string) $data['direccion']) : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('email inválido');
        }
        if ($nombre === '') {
            throw new ValidationException('nombre_completo es obligatorio');
        }
        if (strlen($password) < 8) {
            throw new ValidationException('password debe tener al menos 8 caracteres');
        }
        if (!UserRoles::isValid($rol)) {
            throw new ValidationException('rol no válido', ['rol' => UserRoles::ALL]);
        }

        $dup = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetchColumn()) {
            throw new ValidationException('El email ya está registrado');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins = $this->pdo->prepare(
            'INSERT INTO usuarios (email, nombre_completo, password_hash, rol, telefono, direccion, activo)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $ins->execute([$email, $nombre, $hash, $rol, $telefono ?: null, $direccion ?: null]);
        $id = (int) $this->pdo->lastInsertId();

        return $this->obtenerPorId($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $data, bool $esAdmin, int $actorUserId): array
    {
        if (!$esAdmin && $actorUserId !== $id) {
            throw new ForbiddenException('No puede modificar a otro usuario');
        }

        $this->obtenerPorId($id);

        if ($esAdmin) {
            $sets = [];
            $params = [];
            if (array_key_exists('email', $data)) {
                $email = trim((string) $data['email']);
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException('email inválido');
                }
                $chk = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
                $chk->execute([$email, $id]);
                if ($chk->fetchColumn()) {
                    throw new ValidationException('El email ya está en uso');
                }
                $sets[] = 'email = ?';
                $params[] = $email;
            }
            if (array_key_exists('nombre_completo', $data) || array_key_exists('nombre', $data)) {
                $n = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? ''));
                if ($n === '') {
                    throw new ValidationException('nombre_completo no puede quedar vacío');
                }
                $sets[] = 'nombre_completo = ?';
                $params[] = $n;
            }
            if (array_key_exists('rol', $data)) {
                $r = (string) $data['rol'];
                if (!UserRoles::isValid($r)) {
                    throw new ValidationException('rol no válido');
                }
                $sets[] = 'rol = ?';
                $params[] = $r;
            }
            if (array_key_exists('telefono', $data)) {
                $sets[] = 'telefono = ?';
                $params[] = $data['telefono'] !== null && $data['telefono'] !== ''
                    ? trim((string) $data['telefono']) : null;
            }
            if (array_key_exists('direccion', $data)) {
                $sets[] = 'direccion = ?';
                $params[] = $data['direccion'] !== null && $data['direccion'] !== ''
                    ? trim((string) $data['direccion']) : null;
            }
            if (array_key_exists('activo', $data)) {
                $sets[] = 'activo = ?';
                $params[] = (int) ((bool) $data['activo']);
            }
            if (!empty($data['password'])) {
                $pw = (string) $data['password'];
                if (strlen($pw) < 8) {
                    throw new ValidationException('password debe tener al menos 8 caracteres');
                }
                $sets[] = 'password_hash = ?';
                $params[] = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            }
        } else {
            $sets = [];
            $params = [];
            if (array_key_exists('nombre_completo', $data) || array_key_exists('nombre', $data)) {
                $n = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? ''));
                if ($n === '') {
                    throw new ValidationException('nombre_completo no puede quedar vacío');
                }
                $sets[] = 'nombre_completo = ?';
                $params[] = $n;
            }
            if (array_key_exists('telefono', $data)) {
                $sets[] = 'telefono = ?';
                $params[] = $data['telefono'] !== null && $data['telefono'] !== ''
                    ? trim((string) $data['telefono']) : null;
            }
            if (array_key_exists('direccion', $data)) {
                $sets[] = 'direccion = ?';
                $params[] = $data['direccion'] !== null && $data['direccion'] !== ''
                    ? trim((string) $data['direccion']) : null;
            }
            if (!empty($data['password'])) {
                $pw = (string) $data['password'];
                if (strlen($pw) < 8) {
                    throw new ValidationException('password debe tener al menos 8 caracteres');
                }
                $sets[] = 'password_hash = ?';
                $params[] = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            }
            if (array_key_exists('rol', $data) || array_key_exists('email', $data) || array_key_exists('activo', $data)) {
                throw new ForbiddenException('Solo un administrador puede cambiar email, rol o estado activo');
            }
        }

        if ($sets === []) {
            return $this->obtenerPorId($id);
        }

        $params[] = $id;
        $sql = 'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->pdo->prepare($sql)->execute($params);

        return $this->obtenerPorId($id);
    }

    public function desactivar(int $id, int $actorUserId): void
    {
        if ($actorUserId === $id) {
            throw new ValidationException('No puede desactivar su propia cuenta');
        }
        $this->obtenerPorId($id);
        $this->pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$id]);
    }
}
