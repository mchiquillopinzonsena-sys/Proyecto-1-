<?php
/**
 * Validation Exception
 */

namespace App\Exceptions;

class ValidationException extends AppException
{
    public function __construct(string $message = 'Validación fallida', array $errors = [])
    {
        parent::__construct($message, 422, $errors);
    }
}
