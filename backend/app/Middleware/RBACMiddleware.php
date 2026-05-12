<?php

namespace App\Middleware;

use App\Exceptions\ForbiddenException;
use App\Services\RBACService;
use PDO;

/**
 * RBAC Middleware - Dynamic permission-based access control
 *
 * Valida que el usuario tenga los permisos requeridos para acceder a una ruta
 * Reemplaza la anterior implementación hardcoded
 */
class RBACMiddleware
{
    /**
     * Aserta que el usuario tiene un permiso específico
     *
     * @param PDO $pdo
     * @param int $userId
     * @param string $permissionName e.g., "servicios.crear"
     * @throws ForbiddenException
     */
    public static function assertPermission(PDO $pdo, int $userId, string $permissionName): void
    {
        $rbac = new RBACService($pdo);

        if (!$rbac->hasPermission($userId, $permissionName)) {
            throw new ForbiddenException(
                "No tienes permiso para realizar esta acción: {$permissionName}"
            );
        }
    }

    /**
     * Aserta que el usuario tiene AL MENOS UNO de los permisos
     */
    public static function assertAnyPermission(PDO $pdo, int $userId, array $permissionNames): void
    {
        $rbac = new RBACService($pdo);

        if (!$rbac->hasAnyPermission($userId, $permissionNames)) {
            throw new ForbiddenException(
                "No tienes permisos suficientes para esta acción"
            );
        }
    }

    /**
     * Aserta que el usuario tiene TODOS los permisos
     */
    public static function assertAllPermissions(PDO $pdo, int $userId, array $permissionNames): void
    {
        $rbac = new RBACService($pdo);

        if (!$rbac->hasAllPermissions($userId, $permissionNames)) {
            throw new ForbiddenException(
                "Se requieren múltiples permisos para esta acción"
            );
        }
    }

    /**
     * Retorna información de permisos del usuario (para debugging/logs)
     */
    public static function getUserPermissions(PDO $pdo, int $userId): array
    {
        return (new RBACService($pdo))->getUserPermissions($userId);
    }

    /**
     * Retorna información de roles del usuario
     */
    public static function getUserRoles(PDO $pdo, int $userId): array
    {
        return (new RBACService($pdo))->getUserRoles($userId);
    }
}

