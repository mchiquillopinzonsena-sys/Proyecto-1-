<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;
use App\Services\CotizadorService;

class CotizadorController extends BaseController
{
    public function parametros(): void
    {
        $this->authorize('cotizador.leer');

        $cotizador = new CotizadorService($this->pdo);
        $this->success($cotizador->listarParametrosActivos());
    }

    public function equipos(): void
    {
        $this->authorize('cotizador.leer');

        $cotizador = new CotizadorService($this->pdo);
        $this->success($cotizador->listarEquiposActivos());
    }

    public function cotizar(): void
    {
        $this->authorize('cotizador.leer');

        $body = $this->getJSON();
        $equipos = $body['equipos'] ?? null;
        if (!is_array($equipos)) {
            throw new ValidationException('equipos debe ser un arreglo');
        }

        $cotizador = new CotizadorService($this->pdo);
        $this->success($cotizador->cotizar($equipos), 'Cotizacion simulada');
    }

    public function updateParametro(int $id): void
    {
        $this->authorize('cotizador.actualizar');

        $cotizador = new CotizadorService($this->pdo);
        $this->success($cotizador->actualizarParametro($id, $this->getJSON()), 'Parametro actualizado');
    }

    public function updateEquipo(int $id): void
    {
        $this->authorize('cotizador.actualizar');

        $cotizador = new CotizadorService($this->pdo);
        $this->success($cotizador->actualizarEquipo($id, $this->getJSON()), 'Equipo actualizado');
    }
}
