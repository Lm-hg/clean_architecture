<?php

namespace App\Application\dtos\user;

use App\Domain\ValueObjects\User\Email;
use App\Domain\ValueObjects\User\Password;
use App\Domain\ValueObjects\User\Role;

class CreateUserDto
{
    public string $firstName;
    public string $name;
    public Email $email;
    public Password $password;
    public Role $role;

    public function __construct(string $firstName, string $name, Email $email, Password $password, Role $role)
    {
        $this->firstName = $firstName;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
    }
    
}