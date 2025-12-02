<?php
declare(strict_types=1);

namespace App\Domain\Exceptions;

class UnauthorizedAccessException extends \RuntimeException
{
    public function __construct(string $message = "Unauthorized access", int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

