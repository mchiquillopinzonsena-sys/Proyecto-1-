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

    public function listar(?string $rol, ?int $activo, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $off = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        if ($rol !== null && $rol !== '' && UserRoles::isValid($rol)) {
            $where[] = $this->legacyUsuarios() ? 'r.nombre_rol = ?' : 'rol = ?';
            $params[] = $rol;
        }
        if ($activo !== null) {
            $where[] = 'u.activo = ?';
            $params[] = $activo ? 1 : 0;
        }
        $w = implode(' AND ', $where);

        if ($this->legacyUsuarios()) {
            $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE $w");
            $cnt->execute($params);
            $total = (int) $cnt->fetchColumn();

            $st = $this->pdo->prepare(
                "SELECT u.id_usuario AS id, u.email, u.nombre_completo, r.nombre_rol AS rol, u.telefono,
                        NULL AS direccion, u.activo, u.creado_en AS fecha_creacion, u.creado_en AS created_at
                 FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
                 WHERE $w ORDER BY u.id_usuario DESC LIMIT {$perPage} OFFSET {$off}"
            );
            $st->execute($params);

            return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $page, $perPage];
        }

        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE $w");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $st = $this->pdo->prepare(
            "SELECT id, email, nombre_completo, rol, telefono, direccion, activo, fecha_creacion, created_at
             FROM usuarios u WHERE $w ORDER BY id DESC LIMIT {$perPage} OFFSET {$off}"
        );
        $st->execute($params);

        return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $page, $perPage];
    }

    public function obtenerPorId(int $id): array
    {
        $st = $this->pdo->prepare($this->legacyUsuarios()
            ? 'SELECT u.id_usuario AS id, u.email, u.nombre_completo, r.nombre_rol AS rol, u.telefono,
                      NULL AS direccion, u.activo, u.creado_en AS fecha_creacion, u.creado_en AS created_at
               FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
               WHERE u.id_usuario = ? LIMIT 1'
            : 'SELECT id, email, nombre_completo, rol, telefono, direccion, activo, fecha_creacion, created_at
               FROM usuarios WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Usuario no encontrado');
        }

        return $row;
    }

    public function crear(array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $nombre = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? ''));
        $rol = (string) ($data['rol'] ?? UserRoles::CLIENTE);
        $telefono = isset($data['telefono']) ? trim((string) $data['telefono']) : null;
        $direccion = isset($data['direccion']) ? trim((string) $data['direccion']) : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('email invalido');
        }
        if ($nombre === '') {
            throw new ValidationException('nombre_completo es obligatorio');
        }
        if (strlen($password) < 8) {
            throw new ValidationException('password debe tener al menos 8 caracteres');
        }
        if (!UserRoles::isValid($rol)) {
            throw new ValidationException('rol no valido', ['rol' => UserRoles::ALL]);
        }

        $dup = $this->pdo->prepare($this->legacyUsuarios()
            ? 'SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1'
            : 'SELECT id FROM usuarios WHERE email = ? LIMIT 1'
        );
        $dup->execute([$email]);
        if ($dup->fetchColumn()) {
            throw new ValidationException('El email ya esta registrado');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($this->legacyUsuarios()) {
            $roleId = $this->roleId($rol);
            $this->pdo->prepare(
                'INSERT INTO usuarios (nombre_completo, email, password_hash, telefono, id_rol, activo)
                 VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([$nombre, $email, $hash, $telefono ?: null, $roleId]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO usuarios (email, nombre_completo, password_hash, rol, telefono, direccion, activo)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([$email, $nombre, $hash, $rol, $telefono ?: null, $direccion ?: null]);
        }

        return $this->obtenerPorId((int) $this->pdo->lastInsertId());
    }

    public function actualizar(int $id, array $data, bool $esAdmin, int $actorUserId): array
    {
        if (!$esAdmin && $actorUserId !== $id) {
            throw new ForbiddenException('No puede modificar a otro usuario');
        }

        $this->obtenerPorId($id);
        $sets = [];
        $params = [];

        if (!$esAdmin && (array_key_exists('rol', $data) || array_key_exists('email', $data) || array_key_exists('activo', $data))) {
            throw new ForbiddenException('Solo un administrador puede cambiar email, rol o estado activo');
        }

        if ($esAdmin && array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('email invalido');
            }
            $chk = $this->pdo->prepare($this->legacyUsuarios()
                ? 'SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario <> ? LIMIT 1'
                : 'SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1'
            );
            $chk->execute([$email, $id]);
            if ($chk->fetchColumn()) {
                throw new ValidationException('El email ya esta en uso');
            }
            $sets[] = 'email = ?';
            $params[] = $email;
        }

        if (array_key_exists('nombre_completo', $data) || array_key_exists('nombre', $data)) {
            $n = trim((string) ($data['nombre_completo'] ?? $data['nombre'] ?? ''));
            if ($n === '') {
                throw new ValidationException('nombre_completo no puede quedar vacio');
            }
            $sets[] = 'nombre_completo = ?';
            $params[] = $n;
        }

        if ($esAdmin && array_key_exists('rol', $data)) {
            $r = (string) $data['rol'];
            if (!UserRoles::isValid($r)) {
                throw new ValidationException('rol no valido');
            }
            $sets[] = $this->legacyUsuarios() ? 'id_rol = ?' : 'rol = ?';
            $params[] = $this->legacyUsuarios() ? $this->roleId($r) : $r;
        }

        if (array_key_exists('telefono', $data)) {
            $sets[] = 'telefono = ?';
            $params[] = $data['telefono'] !== null && $data['telefono'] !== '' ? trim((string) $data['telefono']) : null;
        }
        if (!$this->legacyUsuarios() && array_key_exists('direccion', $data)) {
            $sets[] = 'direccion = ?';
            $params[] = $data['direccion'] !== null && $data['direccion'] !== '' ? trim((string) $data['direccion']) : null;
        }
        if ($esAdmin && array_key_exists('activo', $data)) {
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

        if ($sets === []) {
            return $this->obtenerPorId($id);
        }

        $params[] = $id;
        $idColumn = $this->legacyUsuarios() ? 'id_usuario' : 'id';
        $this->pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . " WHERE {$idColumn} = ?")->execute($params);

        return $this->obtenerPorId($id);
    }

    public function desactivar(int $id, int $actorUserId): void
    {
        if ($actorUserId === $id) {
            throw new ValidationException('No puede desactivar su propia cuenta');
        }
        $this->obtenerPorId($id);
        $idColumn = $this->legacyUsuarios() ? 'id_usuario' : 'id';
        $this->pdo->prepare("UPDATE usuarios SET activo = 0 WHERE {$idColumn} = ?")->execute([$id]);
    }

    private function roleId(string $rol): int
    {
        $st = $this->pdo->prepare('SELECT id_rol FROM roles WHERE nombre_rol = ? LIMIT 1');
        $st->execute([$rol]);
        $id = $st->fetchColumn();
        if (!$id) {
            throw new ValidationException('rol no valido');
        }

        return (int) $id;
    }

    private function legacyUsuarios(): bool
    {
        return $this->hasColumn('usuarios', 'id_usuario') && !$this->hasColumn('usuarios', 'id');
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
