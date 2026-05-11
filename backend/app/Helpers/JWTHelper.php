<?php
/**
 * JWT Helper - Token generation and validation
 */

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHelper
{
    private static string $secret;
    private static string $algorithm = 'HS256';
    
    public static function setSecret(string $secret): void
    {
        self::$secret = $secret;
    }
    
    public static function generateToken(int $userId, string $role, array $additionalData = []): string
    {
        $issuedAt = time();
        $expiry = $issuedAt + (int)getenv('JWT_EXPIRY', 3600);
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiry,
            'sub' => $userId,
            'role' => $role,
            'iss' => getenv('APP_NAME', 'intermica'),
            'aud' => 'intermica-app',
        ] + $additionalData;
        
        return JWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function generateRefreshToken(int $userId): string
    {
        $issuedAt = time();
        $expiry = $issuedAt + (int)getenv('JWT_REFRESH_EXPIRY', 604800);
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiry,
            'sub' => $userId,
            'type' => 'refresh',
        ];
        
        return JWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(self::$secret, self::$algorithm));
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public static function getTokenPayload(string $token): ?object
    {
        return self::validateToken($token);
    }
}
