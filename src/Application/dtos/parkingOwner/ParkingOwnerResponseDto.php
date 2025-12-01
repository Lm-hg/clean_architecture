<?php

namespace App\Application\dtos\parkingOwner;

use App\Domain\ValueObjects\User\Email;

class ParkingOwnerResponseDto
{
    public string $id;
    public string $firstName;
    public string $lastName;
    public Email $email;
    public \DateTime $createdAt;
    public \DateTime $updatedAt;
    public ?string $token; 

    public function __construct(
        string $id,
        string $firstName,
        string $lastName,
        Email $email,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $token = null
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->token = $token;
    }
}