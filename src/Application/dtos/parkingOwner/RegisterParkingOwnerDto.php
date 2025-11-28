<?php

namespace App\Application\dtos\parkingOwner;

use App\Domain\ValueObjects\User\Email;

class RegisterParkingOwnerDto
{
    public string $firstName;
    public string $lastName;
    public Email $email;
    public string $password;

    public function __construct(
        string $firstName,
        string $lastName,
        Email $email,
        string $password
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->password = $password;
    }
}