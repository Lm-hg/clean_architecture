<?php

namespace App\Application\dtos\auth;

use App\Domain\ObjectValues\User\IdUser;
use App\Domain\ObjectValues\User\Email;
use App\Domain\ObjectValues\User\Role;

class LoginResponseDto
{
    public IdUser $id;
    public string $firstName;
    public string $name;
    public Email $email;
    public Role $role;
    public string $token;

    public function __construct(
        IdUser $id,
        string $firstName,
        string $name,
        Email $email,
        Role $role,
        string $token
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->name = $name;
        $this->email = $email;
        $this->role = $role;
        $this->token = $token;
    }
}

