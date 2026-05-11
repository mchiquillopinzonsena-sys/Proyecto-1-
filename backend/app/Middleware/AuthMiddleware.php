<?php

namespace App\Middleware;

use App\Exceptions\AuthException;
use App\Helpers\JWTHelper;
use App\Http\RequestContext;

class AuthMiddleware
{
    /**
     * @throws AuthException
     */
    public static function requireBearerToken(): RequestContext
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            throw new AuthException('Se requiere encabezado Authorization: Bearer {token}');
        }
        $token = $m[1];
        $payload = JWTHelper::validateToken($token);
        if ($payload === null) {
            throw new AuthException('Token inválido o expirado');
        }
        $userId = (int) ($payload->sub ?? 0);
        $role = (string) ($payload->role ?? '');
        if ($userId < 1 || $role === '') {
            throw new AuthException('Token sin sujeto o rol válido');
        }
        $email = isset($payload->email) ? (string) $payload->email : null;

        return new RequestContext($userId, $role, $token, $email);
    }
}
