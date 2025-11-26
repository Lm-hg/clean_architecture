<?php

namespace App\Domain\ObjectValues\User;

class Email
{
    private string $email;

    public function __construct(string $email)
    {
        if (empty(trim($email))) {
            throw new \InvalidArgumentException("Email cannot be empty.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: " . $email);
        }
        $this->email = $email;
    }

    public function validateEmailFormat(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}