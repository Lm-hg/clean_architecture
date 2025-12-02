<?php

namespace App\Application\dtos\parkingOwner;

use App\Domain\ValueObjects\User\Email;

class LoginParkingOwnerDto
{
    public Email $email;
    public string $password;

    public function __construct(
        Email $email,
        string $password
    ) {
        $this->email = $email;
        $this->password = $password;
    }
}