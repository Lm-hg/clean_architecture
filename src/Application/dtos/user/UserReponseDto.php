<?php

namespace App\Application\dtos\user;

use App\Domain\ValueObjects\User\IdUser;
use App\Domain\ValueObjects\User\Role;
use App\Domain\ValueObjects\User\Email;


class UserReponseDto
{
    public IdUser $id;
    public string $firstName;
    public string $name;
    public Email $email;
    public Role $role;

    public function __construct(IdUser $id, string $firstName, string $name, Email $email, Role $role)
    {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->name = $name;
        $this->email = $email;
        $this->role = $role;
    }
}