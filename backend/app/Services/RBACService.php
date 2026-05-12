<?php
/**
 * RBAC Service - Dynamic role-based access control
 *
 * Reemplaza el hardcoded RBAC con un sistema flexible basado en BD
 * Cachea permisos por usuario para performance
 */

namespace App\Services;

use App\Enums\UserRoles;
use PDO;

class RBACService
{
    private static array $userPermissionsCache = [];
    private const CACHE_DURATION = 3600; // 1 hora

    private const LEGACY_ROLE_PERMISSIONS = [
        UserRoles::ADMIN => ['*'],
        UserRoles::TECNICO => [
            'servicios.leer',
            'servicios.actualizar',
            'servicios.cambiar_estado',
            'stock.leer',
            'cotizador.leer',
        ],
        UserRoles::CLIENTE => [
            'servicios.leer',
            'cuentas.leer',
            'cotizador.leer',
        ],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Verifica si un usuario tiene un permiso específico
     *
     * @param int $userId
     * @param string $permissionName e.g., "servicios.crear"
     * @return bool
     */
    public function hasPermission(int $userId, string $permissionName): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permissionName, $permissions, true);
    }

    /**
     * Verifica si un usuario tiene ALGUNO de los permisos listados
     */
    public function hasAnyPermission(int $userId, array $permissionNames): bool
    {
        $permissions = $this->getUserPermissions($userId);
        foreach ($permissionNames as $name) {
            if (in_array($name, $permissions, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si un usuario tiene TODOS los permisos listados
     */
    public function hasAllPermissions(int $userId, array $permissionNames): bool
    {
        $permissions = $this->getUserPermissions($userId);
        foreach ($permissionNames as $name) {
            if (!in_array($name, $permissions, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtiene todos los permisos de un usuario (cached)
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_perms_{$userId}";

        // Verificar cache en memoria
        if (isset(self::$userPermissionsCache[$cacheKey])) {
            return self::$userPermissionsCache[$cacheKey];
        }

        // Query: obtener todos los permisos del usuario vía roles asignados
        $sql = <<<SQL
            SELECT DISTINCT p.nombre
            FROM permisos p
            INNER JOIN rol_permisos rp ON rp.permiso_id = p.id
            INNER JOIN roles r ON r.id = rp.rol_id
            INNER JOIN usuario_roles ur ON ur.rol_id = r.id
            WHERE ur.usuario_id = ? AND ur.activo = 1 AND r.activo = 1 AND p.activo = 1
            ORDER BY p.nombre
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $permissions = is_array($result) ? $result : [];

        if ($permissions === []) {
            $permissions = $this->getLegacyRolePermissions($userId);
        }

        // Cachear en memoria
        self::$userPermissionsCache[$cacheKey] = $permissions;

        return $permissions;
    }

    /**
     * Obtiene los roles activos de un usuario
     */
    public function getUserRoles(int $userId): array
    {
        $sql = <<<SQL
            SELECT r.id, r.nombre, r.descripcion
            FROM roles r
            INNER JOIN usuario_roles ur ON ur.rol_id = r.id
            WHERE ur.usuario_id = ? AND ur.activo = 1 AND r.activo = 1
            ORDER BY r.nombre
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($roles !== []) {
            return $roles;
        }

        $stmt = $this->pdo->prepare('SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();

        return is_string($role) && $role !== ''
            ? [['id' => 0, 'nombre' => $role, 'descripcion' => 'Rol heredado de usuarios.rol']]
            : [];
    }

    /**
     * Asigna un rol a un usuario
     *
     * @param int $userId ID del usuario
     * @param int $roleId ID del rol
     * @param int $assignedById ID del usuario que asigna
     * @param string|null $reason Razón de la asignación
     * @throws \Exception Si el rol o usuario no existen
     */
    public function assignRoleToUser(int $userId, int $roleId, int $assignedById, ?string $reason = null): void
    {
        // Verificar que usuario y rol existan
        $userStmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
        $userStmt->execute([$userId]);
        $userExists = $userStmt->fetchColumn();

        $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE id = ?');
        $roleStmt->execute([$roleId]);
        $roleExists = $roleStmt->fetchColumn();

        if (!$userExists || !$roleExists) {
            throw new \Exception('Usuario o rol no encontrado');
        }

        // Insertar (ignorar si ya existe)
        $sql = <<<SQL
            INSERT INTO usuario_roles (usuario_id, rol_id, asignado_por, razon, activo)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE activo = 1
        SQL;

        $this->pdo->prepare($sql)->execute([$userId, $roleId, $assignedById, $reason]);

        // Limpiar caché
        unset(self::$userPermissionsCache["user_perms_{$userId}"]);
    }

    /**
     * Revoca un rol de un usuario
     */
    public function revokeRoleFromUser(int $userId, int $roleId): void
    {
        $this->pdo->prepare(
            'UPDATE usuario_roles SET activo = 0 WHERE usuario_id = ? AND rol_id = ?'
        )->execute([$userId, $roleId]);

        unset(self::$userPermissionsCache["user_perms_{$userId}"]);
    }

    /**
     * Limpiar caché (llamar después de cambios de permisos/roles)
     */
    public static function clearCache(int $userId = 0): void
    {
        if ($userId > 0) {
            unset(self::$userPermissionsCache["user_perms_{$userId}"]);
        } else {
            self::$userPermissionsCache = [];
        }
    }

    private function getLegacyRolePermissions(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT rol FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();

        if (!is_string($role) || !isset(self::LEGACY_ROLE_PERMISSIONS[$role])) {
            return [];
        }

        if (self::LEGACY_ROLE_PERMISSIONS[$role] === ['*']) {
            $stmt = $this->pdo->query('SELECT nombre FROM permisos WHERE activo = 1 ORDER BY nombre');
            $permissions = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

            if (is_array($permissions) && $permissions !== []) {
                return $permissions;
            }

            return [
                'admin.configuracion',
                'admin.usuarios',
                'cotizador.actualizar',
                'cotizador.crear',
                'cotizador.leer',
                'cuentas.actualizar',
                'cuentas.crear',
                'cuentas.leer',
                'cuentas.pagar',
                'reportes.exportar',
                'reportes.leer',
                'servicios.actualizar',
                'servicios.cambiar_estado',
                'servicios.crear',
                'servicios.eliminar',
                'servicios.leer',
                'stock.actualizar',
                'stock.leer',
                'usuarios.actualizar',
                'usuarios.crear',
                'usuarios.eliminar',
                'usuarios.leer',
            ];
        }

        return self::LEGACY_ROLE_PERMISSIONS[$role];
    }
}
