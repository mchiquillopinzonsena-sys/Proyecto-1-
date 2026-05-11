<?php
/**
 * BCrypt Helper - Password hashing and verification
 */

namespace App\Helpers;

class BCryptHelper
{
    private static int $cost = 12;
    
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => self::$cost,
        ]);
    }
    
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => self::$cost,
        ]);
    }
}
