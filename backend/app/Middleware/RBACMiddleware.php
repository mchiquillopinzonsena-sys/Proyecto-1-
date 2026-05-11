<?php

namespace App\Middleware;

use App\Enums\UserRoles;
use App\Exceptions\ForbiddenException;

/**
 * RBAC por método + patrón de ruta. El acceso "público" se define en el router (sin JWT).
 */
class RBACMiddleware
{
    /** @var list<array{0:string,1:string,2:list<string>}> método, regex path (sin query), roles */
    private const RULES = [
        ['GET', '#^/api/v1/servicios$#', [UserRoles::ADMIN, UserRoles::TECNICO, UserRoles::CLIENTE]],
        ['GET', '#^/api/v1/servicios/\d+$#', [UserRoles::ADMIN, UserRoles::TECNICO, UserRoles::CLIENTE]],
        ['PATCH', '#^/api/v1/servicios/\d+/estado$#', [UserRoles::ADMIN, UserRoles::TECNICO]],
        ['POST', '#^/api/v1/cuentas$#', [UserRoles::ADMIN]],
    ];

    /**
     * @throws ForbiddenException
     */
    public static function assertRole(string $method, string $path, string $role): void
    {
        $path = rtrim($path, '/') ?: '/';
        foreach (self::RULES as [$m, $regex, $allowed]) {
            if ($m !== $method || !preg_match($regex, $path)) {
                continue;
            }
            if (!in_array($role, $allowed, true)) {
                throw new ForbiddenException("El rol «{$role}» no puede acceder a {$method} {$path}");
            }
            return;
        }
        throw new ForbiddenException('Ruta no configurada en RBAC');
    }
}
