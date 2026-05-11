<?php
/**
 * Base Application Exception
 */

namespace App\Exceptions;

class AppException extends \Exception
{
    protected int $statusCode = 500;
    protected array $errors = [];
    
    public function __construct(string $message = '', int $statusCode = 500, array $errors = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
}
