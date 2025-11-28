<?php

namespace App\Domain\ValueObjects\Price;

class Price
{
    private int $cents;
    private string $currency;

    public function __construct(int $cents, string $currency = 'EUR')
    {
        if (!is_int($cents)) {
            throw new \InvalidArgumentException('Cents must be integer');
        }

        if ($cents < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }

        if (empty(trim($currency))) {
            throw new \InvalidArgumentException('Currency cannot be empty');
        }

        $this->cents = $cents;
        $this->currency = strtoupper($currency);
    }

    public static function fromFloat(float $amount, string $currency = 'EUR'): self
    {
        $cents = (int) round($amount * 100);
        return new self($cents, $currency);
    }

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, $currency);
    }

    public function getCents(): int
    {
        return $this->cents;
    }

    public function getAmount(): float
    {
        return $this->cents / 100.0;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->cents - $other->cents;
        if ($result < 0) {
            throw new \InvalidArgumentException('Resulting price cannot be negative');
        }
        return new self($result, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currencies must match for arithmetic operations');
        }
    }

    public function __toString(): string
    {
        return number_format($this->getAmount(), 2, '.', '') . ' ' . $this->currency;
    }
}
