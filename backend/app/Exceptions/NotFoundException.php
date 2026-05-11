<?php
/**
 * Not Found Exception
 */

namespace App\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(string $message = 'Recurso no encontrado')
    {
        parent::__construct($message, 404);
    }
}
