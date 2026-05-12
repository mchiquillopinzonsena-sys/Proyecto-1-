<?php
/**
 * Tests de RBAC Service
 *
 * Ejecutar: php vendor/bin/phpunit tests/RBACServiceTest.php
 */

namespace Tests;

use App\Services\RBACService;
use PHPUnit\Framework\TestCase;
use PDO;

class RBACServiceTest extends TestCase
{
    private PDO $pdo;
    private RBACService $rbacService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Crear tablas
        $this->pdo->exec(<<<SQL
            CREATE TABLE usuarios (id INTEGER PRIMARY KEY, email TEXT);
            CREATE TABLE permisos (
                id INTEGER PRIMARY KEY,
                nombre TEXT UNIQUE,
                recurso TEXT,
                accion TEXT,
                activo INTEGER DEFAULT 1
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY,
                nombre TEXT UNIQUE,
                activo INTEGER DEFAULT 1
            );
            CREATE TABLE rol_permisos (
                id INTEGER PRIMARY KEY,
                rol_id INTEGER,
                permiso_id INTEGER
            );
            CREATE TABLE usuario_roles (
                id INTEGER PRIMARY KEY,
                usuario_id INTEGER,
                rol_id INTEGER,
                activo INTEGER DEFAULT 1
            );
        SQL);

        // Datos de test
        $this->pdo->prepare('INSERT INTO usuarios (id, email) VALUES (?, ?)')
            ->execute([1, 'user@test.com']);

        $this->pdo->prepare('INSERT INTO permisos (id, nombre, recurso, accion, activo) VALUES (?, ?, ?, ?, 1)')
            ->execute([1, 'servicios.crear', 'servicios', 'crear', 1]);
        $this->pdo->prepare('INSERT INTO permisos (id, nombre, recurso, accion, activo) VALUES (?, ?, ?, ?, 1)')
            ->execute([2, 'servicios.leer', 'servicios', 'leer', 1]);
        $this->pdo->prepare('INSERT INTO permisos (id, nombre, recurso, accion, activo) VALUES (?, ?, ?, ?, 1)')
            ->execute([3, 'usuarios.eliminar', 'usuarios', 'eliminar', 1]);

        $this->pdo->prepare('INSERT INTO roles (id, nombre, activo) VALUES (?, ?, 1)')
            ->execute([1, 'admin']);
        $this->pdo->prepare('INSERT INTO roles (id, nombre, activo) VALUES (?, ?, 1)')
            ->execute([2, 'cliente']);

        // Admin tiene todos los permisos
        $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)')
            ->execute([1, 1]);
        $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)')
            ->execute([1, 2]);
        $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)')
            ->execute([1, 3]);

        // Cliente solo puede leer
        $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)')
            ->execute([2, 2]);

        // Asignar roles a usuario
        $this->pdo->prepare('INSERT INTO usuario_roles (usuario_id, rol_id, activo) VALUES (?, ?, 1)')
            ->execute([1, 2]);

        $this->rbacService = new RBACService($this->pdo);
    }

    public function testUserHasPermission(): void
    {
        $this->assertTrue($this->rbacService->hasPermission(1, 'servicios.leer'));
    }

    public function testUserDoesNotHavePermission(): void
    {
        $this->assertFalse($this->rbacService->hasPermission(1, 'servicios.crear'));
        $this->assertFalse($this->rbacService->hasPermission(1, 'usuarios.eliminar'));
    }

    public function testGetUserPermissions(): void
    {
        $permissions = $this->rbacService->getUserPermissions(1);

        $this->assertIsArray($permissions);
        $this->assertContains('servicios.leer', $permissions);
        $this->assertNotContains('servicios.crear', $permissions);
    }

    public function testGetUserRoles(): void
    {
        $roles = $this->rbacService->getUserRoles(1);

        $this->assertIsArray($roles);
        $this->assertCount(1, $roles);
        $this->assertEquals('cliente', $roles[0]['nombre']);
    }

    public function testHasAnyPermission(): void
    {
        // Usuario tiene 'servicios.leer', que está en el array
        $this->assertTrue(
            $this->rbacService->hasAnyPermission(1, ['servicios.crear', 'servicios.leer'])
        );

        // Usuario no tiene ninguno de estos
        $this->assertFalse(
            $this->rbacService->hasAnyPermission(1, ['usuarios.eliminar', 'usuarios.crear'])
        );
    }

    public function testHasAllPermissions(): void
    {
        // Usuario tiene ambos? No, solo 'servicios.leer'
        $this->assertFalse(
            $this->rbacService->hasAllPermissions(1, ['servicios.crear', 'servicios.leer'])
        );

        // Usuario tiene solo 'servicios.leer'? Sí
        $this->assertTrue(
            $this->rbacService->hasAllPermissions(1, ['servicios.leer'])
        );
    }
}
