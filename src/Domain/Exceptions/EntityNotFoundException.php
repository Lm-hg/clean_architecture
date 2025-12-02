<?php
declare(strict_types=1);

namespace App\Domain\Exceptions;

class EntityNotFoundException extends \RuntimeException
{
    public function __construct(string $message = "Entity not found", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

