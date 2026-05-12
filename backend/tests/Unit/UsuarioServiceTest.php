<?php

namespace Tests;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Services\UsuarioService;
use PDO;
use PHPUnit\Framework\TestCase;

class UsuarioServiceTest extends TestCase
{
    private PDO $pdo;
    private UsuarioService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<SQL
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                nombre_completo TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                rol TEXT NOT NULL,
                telefono TEXT,
                direccion TEXT,
                activo INTEGER DEFAULT 1,
                fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        SQL);

        $this->pdo->prepare(
            'INSERT INTO usuarios (email, nombre_completo, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)'
        )->execute(['admin@test.com', 'Admin', password_hash('Password123', PASSWORD_BCRYPT), 'admin']);
        $this->pdo->prepare(
            'INSERT INTO usuarios (email, nombre_completo, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)'
        )->execute(['cliente@test.com', 'Cliente', password_hash('Password123', PASSWORD_BCRYPT), 'cliente']);

        $this->service = new UsuarioService($this->pdo);
    }

    public function testCrearUsuarioValido(): void
    {
        $usuario = $this->service->crear([
            'email' => 'tecnico@test.com',
            'nombre_completo' => 'Tecnico Uno',
            'password' => 'Password123',
            'rol' => 'tecnico',
        ]);

        $this->assertSame('tecnico@test.com', $usuario['email']);
        $this->assertSame('tecnico', $usuario['rol']);
        $this->assertArrayNotHasKey('password_hash', $usuario);
    }

    public function testCrearUsuarioConEmailDuplicadoFalla(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->crear([
            'email' => 'admin@test.com',
            'nombre_completo' => 'Otro Admin',
            'password' => 'Password123',
            'rol' => 'admin',
        ]);
    }

    public function testNoAdminNoPuedeModificarOtroUsuario(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->service->actualizar(1, ['nombre_completo' => 'Cambio'], false, 2);
    }

    public function testNoAdminNoPuedeCambiarEmail(): void
    {
        $this->expectException(ForbiddenException::class);

        $this->service->actualizar(2, ['email' => 'nuevo@test.com'], false, 2);
    }

    public function testAdminNoPuedeDesactivarse(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->desactivar(1, 1);
    }
}
