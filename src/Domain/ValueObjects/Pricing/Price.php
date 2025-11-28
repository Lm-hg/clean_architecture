<?php

namespace App\Domain\ValueObjects\Pricing;

class Price extends \App\Domain\ValueObjects\Price\Price
{
    // Adapter class to keep backwards compatibility with code expecting
    // App\Domain\ValueObjects\Pricing\Price while reusing the canonical Price VO.

    public static function fromFloat(float $amount, string $currency = 'EUR'): self
    {
        $parent = parent::fromFloat($amount, $currency);
        return new self($parent->getCents(), $parent->getCurrency());
    }

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        $parent = parent::fromCents($cents, $currency);
        return new self($parent->getCents(), $parent->getCurrency());
    }
}
