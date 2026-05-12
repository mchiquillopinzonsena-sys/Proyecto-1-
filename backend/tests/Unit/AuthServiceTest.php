<?php
/**
 * Tests de Autenticación y JWT
 *
 * Ejecutar: php vendor/bin/phpunit tests/AuthServiceTest.php
 */

namespace Tests;

use App\Helpers\JWTHelper;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;
use PDO;

class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthService $authService;

    protected function setUp(): void
    {
        // Inicializar PDO con BD de test
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear tabla de usuarios
        $this->pdo->exec(<<<SQL
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY,
                email TEXT UNIQUE,
                password_hash TEXT,
                rol TEXT,
                activo INTEGER DEFAULT 1
            );
            CREATE TABLE sesiones_jwt (
                id INTEGER PRIMARY KEY,
                usuario_id INTEGER,
                token_hash TEXT UNIQUE,
                refresh_token_hash TEXT,
                ip_address TEXT,
                user_agent TEXT,
                fecha_inicio DATETIME,
                fecha_expiracion DATETIME,
                activa INTEGER,
                fecha_cierre DATETIME
            );
        SQL);

        // Insertar usuario de test
        $hashedPassword = password_hash('Test123!', PASSWORD_BCRYPT, ['cost' => 12]);
        $this->pdo->prepare(
            'INSERT INTO usuarios (id, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)'
        )->execute([1, 'test@example.com', $hashedPassword, 'admin']);

        $this->authService = new AuthService($this->pdo);
        JWTHelper::setSecret('test-secret-key');
    }

    public function testLoginWithValidCredentials(): void
    {
        $result = $this->authService->login('test@example.com', 'Test123!');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertEquals('Bearer', $result['token_type']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->expectException(\App\Exceptions\AuthException::class);
        $this->authService->login('test@example.com', 'WrongPassword');
    }

    public function testLoginWithInvalidEmail(): void
    {
        $this->expectException(\App\Exceptions\AuthException::class);
        $this->authService->login('nonexistent@example.com', 'Test123!');
    }

    public function testJWTTokenGeneration(): void
    {
        $token = JWTHelper::generateToken(1, 'admin', ['email' => 'test@example.com']);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Validar que el token se puede decodificar
        $payload = JWTHelper::validateToken($token);
        $this->assertNotNull($payload);
        $this->assertEquals(1, $payload->sub);
        $this->assertEquals('admin', $payload->role);
    }

    public function testInvalidToken(): void
    {
        $payload = JWTHelper::validateToken('invalid.token.here');
        $this->assertNull($payload);
    }

    public function testExpiredToken(): void
    {
        // Token expirado hace 1 hora
        $payload = [
            'sub' => 1,
            'role' => 'admin',
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ];

        $token = \Firebase\JWT\JWT::encode(
            $payload,
            'test-secret-key',
            'HS256'
        );

        $result = JWTHelper::validateToken($token);
        $this->assertNull($result);
    }
}
