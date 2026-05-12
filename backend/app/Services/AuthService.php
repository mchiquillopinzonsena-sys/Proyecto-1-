<?php

namespace App\Services;

use App\Exceptions\AuthException;
use App\Helpers\JWTHelper;
use PDO;

class AuthService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     * @throws AuthException
     */
    public function login(string $email, string $password): array
    {
        $stmt = $this->pdo->prepare($this->legacyUsuarios()
            ? 'SELECT u.id_usuario AS id, u.email, u.password_hash, r.nombre_rol AS rol, u.activo
               FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
               WHERE u.email = ? LIMIT 1'
            : 'SELECT id, email, password_hash, rol, activo FROM usuarios WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['activo']) {
            throw new AuthException('Credenciales invalidas');
        }
        if (!password_verify($password, $user['password_hash'])) {
            throw new AuthException('Credenciales invalidas');
        }

        $accessToken = JWTHelper::generateToken((int) $user['id'], (string) $user['rol'], [
            'email' => $user['email'],
        ]);
        $refreshToken = JWTHelper::generateRefreshToken((int) $user['id']);

        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $refreshToken);
        $expiryTs = time() + (int) (getenv('JWT_EXPIRY') ?: 3600);
        $expiry = gmdate('Y-m-d H:i:s', $expiryTs);

        $this->storeSession((int) $user['id'], $accessHash, $refreshHash, $expiry);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) (getenv('JWT_EXPIRY') ?: 3600),
            'token_type' => 'Bearer',
        ];
    }

    /**
     * @throws AuthException
     */
    public function refresh(string $refreshToken): array
    {
        $payload = JWTHelper::validateToken($refreshToken);
        if ($payload === null || (($payload->type ?? '') !== 'refresh')) {
            throw new AuthException('Refresh token invalido');
        }

        $userId = (int) ($payload->sub ?? 0);
        $stmt = $this->pdo->prepare($this->legacyUsuarios()
            ? 'SELECT u.id_usuario AS id, u.email, r.nombre_rol AS rol, u.activo
               FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
               WHERE u.id_usuario = ? LIMIT 1'
            : 'SELECT id, email, rol, activo FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['activo']) {
            throw new AuthException('Usuario no valido');
        }

        $accessToken = JWTHelper::generateToken((int) $user['id'], (string) $user['rol'], [
            'email' => $user['email'],
        ]);
        $newRefresh = JWTHelper::generateRefreshToken((int) $user['id']);
        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $newRefresh);
        $expiryTs = time() + (int) (getenv('JWT_EXPIRY') ?: 3600);
        $expiry = gmdate('Y-m-d H:i:s', $expiryTs);

        if ($this->hasColumn('sesiones_jwt', 'activa')) {
            $this->pdo->prepare(
                'UPDATE sesiones_jwt SET activa = 0, fecha_cierre = NOW() WHERE usuario_id = ? AND activa = 1'
            )->execute([$userId]);
        } else {
            $this->pdo->prepare(
                'UPDATE sesiones_jwt SET revocado = 1 WHERE id_usuario = ? AND revocado = 0'
            )->execute([$userId]);
        }

        $this->storeSession($userId, $accessHash, $refreshHash, $expiry);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefresh,
            'expires_in' => (int) (getenv('JWT_EXPIRY') ?: 3600),
            'token_type' => 'Bearer',
        ];
    }

    public function revokeByAccessToken(string $accessToken): void
    {
        if (!$this->hasColumn('sesiones_jwt', 'token_hash')) {
            return;
        }

        $h = hash('sha256', $accessToken);
        $this->pdo->prepare('UPDATE sesiones_jwt SET activa = 0, fecha_cierre = NOW() WHERE token_hash = ?')->execute([$h]);
    }

    /**
     * @throws AuthException
     */
    public function assertActiveAccessToken(string $accessToken): void
    {
        if (!$this->hasColumn('sesiones_jwt', 'token_hash')) {
            return;
        }

        $h = hash('sha256', $accessToken);
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sesiones_jwt WHERE token_hash = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1'
        );
        $stmt->execute([$h]);
        if (!$stmt->fetchColumn()) {
            throw new AuthException('Sesion invalida o cerrada');
        }
    }

    private function storeSession(int $userId, string $accessHash, string $refreshHash, string $expiry): void
    {
        if ($this->hasColumn('sesiones_jwt', 'token_hash')) {
            $this->pdo->prepare(
                'INSERT INTO sesiones_jwt (usuario_id, token_hash, refresh_token_hash, ip_address, user_agent, fecha_expiracion, activa)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $userId,
                $accessHash,
                $refreshHash,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $expiry,
            ]);
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO sesiones_jwt (id_usuario, refresh_token_hash, ip_address, user_agent, expira_en, revocado)
             VALUES (?, ?, ?, ?, ?, 0)'
        )->execute([
            $userId,
            $refreshHash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiry,
        ]);
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
