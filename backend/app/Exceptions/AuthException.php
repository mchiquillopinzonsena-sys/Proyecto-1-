<?php
/**
 * Authentication Exception
 */

namespace App\Exceptions;

class AuthException extends AppException
{
    public function __construct(string $message = 'Autenticación fallida', array $errors = [])
    {
        parent::__construct($message, 401, $errors);
    }
}
