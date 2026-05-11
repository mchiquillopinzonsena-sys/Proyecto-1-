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
