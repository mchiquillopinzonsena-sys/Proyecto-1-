<?php

namespace Tests;

use App\Services\RBACService;
use PDO;
use PHPUnit\Framework\TestCase;

class RBACLegacyFallbackTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        RBACService::clearCache();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<SQL
            CREATE TABLE usuarios (id INTEGER PRIMARY KEY, email TEXT, rol TEXT, activo INTEGER DEFAULT 1);
            CREATE TABLE permisos (id INTEGER PRIMARY KEY, nombre TEXT UNIQUE, activo INTEGER DEFAULT 1);
            CREATE TABLE roles (id INTEGER PRIMARY KEY, nombre TEXT UNIQUE, descripcion TEXT, activo INTEGER DEFAULT 1);
            CREATE TABLE rol_permisos (id INTEGER PRIMARY KEY, rol_id INTEGER, permiso_id INTEGER);
            CREATE TABLE usuario_roles (id INTEGER PRIMARY KEY, usuario_id INTEGER, rol_id INTEGER, activo INTEGER DEFAULT 1);
        SQL);

        foreach (['servicios.leer', 'cotizador.leer', 'usuarios.crear'] as $idx => $permiso) {
            $this->pdo->prepare('INSERT INTO permisos (id, nombre, activo) VALUES (?, ?, 1)')
                ->execute([$idx + 1, $permiso]);
        }

        $this->pdo->prepare('INSERT INTO usuarios (id, email, rol, activo) VALUES (?, ?, ?, 1)')
            ->execute([1, 'cliente@test.com', 'cliente']);
        $this->pdo->prepare('INSERT INTO usuarios (id, email, rol, activo) VALUES (?, ?, ?, 1)')
            ->execute([2, 'admin@test.com', 'admin']);
    }

    public function testClienteHeredaPermisosDesdeUsuariosRol(): void
    {
        $rbac = new RBACService($this->pdo);

        $this->assertTrue($rbac->hasPermission(1, 'servicios.leer'));
        $this->assertTrue($rbac->hasPermission(1, 'cotizador.leer'));
        $this->assertFalse($rbac->hasPermission(1, 'usuarios.crear'));
    }

    public function testAdminHeredaPermisosDesdeCatalogo(): void
    {
        $rbac = new RBACService($this->pdo);

        $this->assertTrue($rbac->hasPermission(2, 'usuarios.crear'));
    }
}
