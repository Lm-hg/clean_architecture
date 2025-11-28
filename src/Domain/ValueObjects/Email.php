<?php

namespace App\Domain\ValueObjects;

class Email
{
    private string $value;

    public function __construct(string $email)
    {
        $email = trim($email);
        if (empty($email)) {
            throw new \InvalidArgumentException('Email cannot be empty');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        $this->value = $email;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return strtolower($this->value) === strtolower($other->getValue());
    }
}
