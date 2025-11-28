<?php

namespace App\Domain\ValueObjects\User;

class Role
{
    private string $role;
    
    public function __construct(string $role)
    {
        $this->role = $role;
    }

    public function getRole(): string
    {
        return $this->role;
    }
}