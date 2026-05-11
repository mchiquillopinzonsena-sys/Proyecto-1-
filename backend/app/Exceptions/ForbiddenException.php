<?php
/**
 * Forbidden Exception (Access Denied)
 */

namespace App\Exceptions;

class ForbiddenException extends AppException
{
    public function __construct(string $message = 'Acceso denegado')
    {
        parent::__construct($message, 403);
    }
}
