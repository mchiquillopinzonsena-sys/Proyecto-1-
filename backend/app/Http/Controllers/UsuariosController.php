<?php

namespace App\Http\Controllers;

use App\Enums\UserRoles;
use App\Exceptions\ForbiddenException;
use App\Helpers\ResponseHelper;
use App\Services\UsuarioService;

class UsuariosController extends BaseController
{
    public function me(): void
    {
        $usuarios = new UsuarioService($this->pdo);
        $this->success($usuarios->obtenerPorId($this->ctx->userId));
    }

    public function index(): void
    {
        $this->authorize('usuarios.leer');

        $usuarios = new UsuarioService($this->pdo);
        $rol = isset($_GET['rol']) ? (string) $_GET['rol'] : null;
        $activo = array_key_exists('activo', $_GET) ? (int) (bool) $_GET['activo'] : null;
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 30;

        [$rows, $total, $page, $perPage] = $usuarios->listar($rol, $activo, $page, $perPage);

        http_response_code(200);
        echo json_encode(
            ResponseHelper::paginated($rows, $page, $perPage, $total, 'Usuarios'),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        exit;
    }

    public function show(int $id): void
    {
        if ($id !== $this->ctx->userId) {
            $this->authorize('usuarios.leer');
        }

        $usuarios = new UsuarioService($this->pdo);
        $this->success($usuarios->obtenerPorId($id));
    }

    public function store(): void
    {
        $this->authorize('usuarios.crear');

        $usuarios = new UsuarioService($this->pdo);
        $this->success($usuarios->crear($this->getJSON()), 'Usuario creado', 201);
    }

    public function update(int $id): void
    {
        $body = $this->getJSON();
        $esAdmin = $this->ctx->role === UserRoles::ADMIN;

        if ($id !== $this->ctx->userId) {
            $this->authorize('usuarios.actualizar');
        }

        $usuarios = new UsuarioService($this->pdo);
        $updated = $usuarios->actualizar($id, $body, $esAdmin, $this->ctx->userId);
        $this->success($updated, 'Usuario actualizado');
    }

    public function destroy(int $id): void
    {
        $this->authorize('usuarios.eliminar');

        if ($this->ctx->role !== UserRoles::ADMIN) {
            throw new ForbiddenException('Solo un administrador puede desactivar usuarios');
        }

        $usuarios = new UsuarioService($this->pdo);
        $usuarios->desactivar($id, $this->ctx->userId);
        $this->success(['id' => $id, 'activo' => false], 'Usuario desactivado');
    }
}
