<?php

namespace App\Application\dtos\user;

use App\Domain\ValueObjects\User\Password;
use App\Domain\ValueObjects\User\Role;

class UpdateUserDto
{
    public string $firstName;
    public string $name;
    public Password $password;
    public Role $role;


    public function __construct(string $firstName, string $name, Password $password, Role $role)
    {
        $this->firstName = $firstName;
        $this->name = $name;
        $this->password = $password;
        $this->role = $role;
    }
}
