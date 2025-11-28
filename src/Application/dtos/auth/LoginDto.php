<?php

namespace App\Application\dtos\auth;

use App\Domain\ValueObjects\User\Email;

class LoginDto
{
    public Email $email;
    public string $password;

    public function __construct(Email $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;
    }
}

