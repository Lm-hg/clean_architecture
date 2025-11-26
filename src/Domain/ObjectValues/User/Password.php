<?php

namespace App\Domain\ObjectValues\User;

class Password
{
    private string $password;
    
    public function __construct(string $password)
    {
        if (empty($password)) {
            throw new \InvalidArgumentException("Password cannot be empty.");
        }
        
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException("Password must be at least 8 characters long.");
        }
        
        $this->password = $password;
    }

    public function validatePasswordFormat(): bool
    {
        return strlen($this->password) >= 8;
    }

    public function __toString(): string
    {
        return '********';
    }

    public function matchHash(string $hash): bool
    {
        return password_verify($this->password, $hash);
    }

    public function getPlainPassword(): string
    {
        return $this->password;
    }
}