<?php

namespace App\Services;

use App\Exceptions\AuthException;
use App\Helpers\JWTHelper;
use Database\Database;
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
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, rol, activo FROM usuarios WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['activo']) {
            throw new AuthException('Credenciales inválidas');
        }
        if (!password_verify($password, $user['password_hash'])) {
            throw new AuthException('Credenciales inválidas');
        }

        $accessToken = JWTHelper::generateToken((int) $user['id'], (string) $user['rol'], [
            'email' => $user['email'],
        ]);
        $refreshToken = JWTHelper::generateRefreshToken((int) $user['id']);

        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $refreshToken);
        $expiryTs = time() + (int) (getenv('JWT_EXPIRY') ?: 3600);
        $expiry = gmdate('Y-m-d H:i:s', $expiryTs);

        $ins = $this->pdo->prepare(
            'INSERT INTO sesiones_jwt (usuario_id, token_hash, refresh_token_hash, ip_address, user_agent, fecha_expiracion, activa)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $ins->execute([
            $user['id'],
            $accessHash,
            $refreshHash,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiry,
        ]);

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
            throw new AuthException('Refresh token inválido');
        }
        $userId = (int) ($payload->sub ?? 0);
        $stmt = $this->pdo->prepare(
            'SELECT id, email, rol, activo FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int) $user['activo']) {
            throw new AuthException('Usuario no válido');
        }
        $accessToken = JWTHelper::generateToken((int) $user['id'], (string) $user['rol'], [
            'email' => $user['email'],
        ]);
        $newRefresh = JWTHelper::generateRefreshToken((int) $user['id']);
        $accessHash = hash('sha256', $accessToken);
        $refreshHash = hash('sha256', $newRefresh);
        $expiryTs = time() + (int) (getenv('JWT_EXPIRY') ?: 3600);
        $expiry = gmdate('Y-m-d H:i:s', $expiryTs);

        $this->pdo->prepare(
            'UPDATE sesiones_jwt SET activa = 0, fecha_cierre = NOW() WHERE usuario_id = ? AND activa = 1'
        )->execute([$userId]);

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

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefresh,
            'expires_in' => (int) (getenv('JWT_EXPIRY') ?: 3600),
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Opcional: invalida sesiones cuyo hash coincide con el access token actual.
     */
    public function revokeByAccessToken(string $accessToken): void
    {
        $h = hash('sha256', $accessToken);
        $this->pdo->prepare('UPDATE sesiones_jwt SET activa = 0, fecha_cierre = NOW() WHERE token_hash = ?')->execute([$h]);
    }

    /**
     * Comprueba que el access token exista y esté activo (sesión en BD).
     *
     * @throws AuthException
     */
    public function assertActiveAccessToken(string $accessToken): void
    {
        $h = hash('sha256', $accessToken);
        $stmt = $this->pdo->prepare(
            'SELECT id FROM sesiones_jwt WHERE token_hash = ? AND activa = 1 AND fecha_expiracion > NOW() LIMIT 1'
        );
        $stmt->execute([$h]);
        if (!$stmt->fetchColumn()) {
            throw new AuthException('Sesión inválida o cerrada');
        }
    }
}
