<?php

namespace Tests;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Services\CotizadorService;
use PDO;
use PHPUnit\Framework\TestCase;

class CotizadorServiceTest extends TestCase
{
    private PDO $pdo;
    private CotizadorService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<SQL
            CREATE TABLE parametros_cotizador (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT UNIQUE NOT NULL,
                nombre TEXT NOT NULL,
                descripcion TEXT,
                tipo_parametro TEXT,
                valor_base REAL,
                unidad TEXT,
                activo INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE parametros_equipos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre_equipo TEXT NOT NULL,
                tipo_equipo TEXT,
                valor_inspeccion_base REAL NOT NULL,
                tiempo_inspeccion_minutos INTEGER,
                complejidad TEXT,
                activo INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        SQL);

        $this->pdo->prepare(
            'INSERT INTO parametros_equipos (id, nombre_equipo, tipo_equipo, valor_inspeccion_base, tiempo_inspeccion_minutos, complejidad, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([1, 'Caldera', 'caldera', 100000, 60, 'media', 1]);

        $this->pdo->prepare(
            'INSERT INTO parametros_cotizador (codigo, nombre, tipo_parametro, valor_base, unidad, activo)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute(['RECARGO', 'Recargo', 'porcentaje', 10, '%', 1]);
        $this->pdo->prepare(
            'INSERT INTO parametros_cotizador (codigo, nombre, tipo_parametro, valor_base, unidad, activo)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute(['FIJO', 'Fijo', 'valor_fijo', 5000, 'COP', 1]);

        $this->service = new CotizadorService($this->pdo);
    }

    public function testCotizarCalculaTotales(): void
    {
        $result = $this->service->cotizar([
            ['equipo_id' => 1, 'cantidad' => 2],
        ]);

        $this->assertSame(200000.0, $result['subtotal_equipos']);
        $this->assertSame(225000.0, $result['subtotal_con_ajustes']);
        $this->assertSame(42750.0, $result['iva_19']);
        $this->assertSame(267750.0, $result['total']);
    }

    public function testCotizarSinEquiposFalla(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->cotizar([]);
    }

    public function testCotizarEquipoInexistenteFalla(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->cotizar([['equipo_id' => 99, 'cantidad' => 1]]);
    }

    public function testActualizarParametro(): void
    {
        $parametro = $this->service->actualizarParametro(1, ['valor_base' => 15]);
        $this->assertSame(15.0, (float) $parametro['valor_base']);
    }
}
