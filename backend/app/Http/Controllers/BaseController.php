<?php
/**
 * Base Controller - Clase base para todos los controllers
 *
 * Proporciona métodos comunes para validación, autorización, y respuestas
 */

namespace App\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\ResponseHelper;
use App\Http\RequestContext;
use App\Middleware\RBACMiddleware;
use PDO;

abstract class BaseController
{
    protected PDO $pdo;
    protected RequestContext $ctx;

    public function __construct(PDO $pdo, RequestContext $ctx)
    {
        $this->pdo = $pdo;
        $this->ctx = $ctx;
    }

    /**
     * Valida que el usuario tenga un permiso específico
     *
     * @throws ForbiddenException
     */
    protected function authorize(string $permission): void
    {
        RBACMiddleware::assertPermission($this->pdo, $this->ctx->userId, $permission);
    }

    /**
     * Valida que tenga AL MENOS UNO de los permisos
     */
    protected function authorizeAny(array $permissions): void
    {
        RBACMiddleware::assertAnyPermission($this->pdo, $this->ctx->userId, $permissions);
    }

    /**
     * Valida que tenga TODOS los permisos
     */
    protected function authorizeAll(array $permissions): void
    {
        RBACMiddleware::assertAllPermissions($this->pdo, $this->ctx->userId, $permissions);
    }

    /**
     * Valida datos de entrada contra un esquema
     *
     * @throws ValidationException
     */
    protected function validate(array $data, array $rules): void
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldErrors = [];

            foreach ($fieldRules as $rule) {
                $this->validateRule($field, $value, $rule, $fieldErrors);
            }

            if ($fieldErrors) {
                $errors[$field] = $fieldErrors;
            }
        }

        if ($errors) {
            throw new ValidationException('Validación fallida', $errors);
        }
    }

    /**
     * Valida una regla individual
     */
    private function validateRule(string $field, mixed $value, string $rule, array &$errors): void
    {
        if (str_starts_with($rule, 'required')) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $errors[] = "{$field} es obligatorio";
            }
            return;
        }

        if (str_starts_with($rule, 'email')) {
            if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$field} debe ser un email válido";
            }
            return;
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if ($value && strlen((string) $value) < $min) {
                $errors[] = "{$field} debe tener al menos {$min} caracteres";
            }
            return;
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if ($value && strlen((string) $value) > $max) {
                $errors[] = "{$field} no puede exceder {$max} caracteres";
            }
            return;
        }

        if (str_starts_with($rule, 'in:')) {
            $values = explode(',', substr($rule, 3));
            if ($value && !in_array($value, $values, true)) {
                $errors[] = "{$field} debe ser uno de: " . implode(', ', $values);
            }
            return;
        }

        if ($rule === 'numeric' || $rule === 'integer') {
            if ($value && !is_numeric($value)) {
                $errors[] = "{$field} debe ser un número";
            }
            return;
        }

        if ($rule === 'date') {
            if ($value && !strtotime($value)) {
                $errors[] = "{$field} debe ser una fecha válida";
            }
            return;
        }

        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            if ($value && !preg_match($pattern, (string) $value)) {
                $errors[] = "{$field} tiene un formato inválido";
            }
            return;
        }
    }

    /**
     * Obtiene datos JSON del body
     */
    protected function getJSON(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Responde con JSON success
     */
    protected function success(mixed $data = null, string $message = '', int $code = 200): void
    {
        http_response_code($code);
        echo json_encode(ResponseHelper::success($data, $message), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Responde con JSON error
     */
    protected function error(string $message, int $code = 400, array $errors = []): void
    {
        http_response_code($code);
        echo json_encode(ResponseHelper::error($message, $code, $errors), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Obtiene un registro o lanza NotFoundException
     */
    protected function findOrFail(string $table, int $id, ?string $column = 'id'): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE {$column} = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException("Registro no encontrado");
        }

        return $row;
    }
}
