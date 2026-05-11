<?php
/**
 * User Roles Enumeration
 */

namespace App\Enums;

class UserRoles
{
    const ADMIN = 'admin';

    const TECNICO = 'tecnico';

    const CLIENTE = 'cliente';

    /** Rutas sin JWT (allowlist en router); no es valor en ENUM usuarios.rol */
    const PUBLICO = 'publico';

    const ALL = [
        self::ADMIN,
        self::TECNICO,
        self::CLIENTE,
    ];
    
    public static function isValid(string $role): bool
    {
        return in_array($role, self::ALL);
    }
}
