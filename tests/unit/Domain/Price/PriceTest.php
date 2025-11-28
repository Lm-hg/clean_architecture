<?php

namespace Tests\Unit\Domain\Price;

use PHPUnit\Framework\TestCase;
use App\Domain\ValueObjects\Price\Price;

class PriceTest extends TestCase
{
    public function test_create_from_float_and_cents(): void
    {
        $p1 = Price::fromFloat(12.34, 'EUR');
        $this->assertEquals(1234, $p1->getCents());
        $this->assertEquals(12.34, $p1->getAmount());
        $this->assertEquals('EUR', $p1->getCurrency());

        $p2 = Price::fromCents(500, 'eur');
        $this->assertEquals(500, $p2->getCents());
        $this->assertEquals(5.00, $p2->getAmount());
        $this->assertEquals('EUR', $p2->getCurrency());
    }

    public function test_add_and_subtract(): void
    {
        $a = Price::fromCents(1000, 'USD'); // 10.00 USD
        $b = Price::fromCents(250, 'USD');  // 2.50 USD

        $sum = $a->add($b);
        $this->assertEquals(1250, $sum->getCents());

        $diff = $a->subtract($b);
        $this->assertEquals(750, $diff->getCents());
    }

    public function test_equals_and_string(): void
    {
        $x = Price::fromFloat(1.23, 'EUR');
        $y = Price::fromCents(123, 'EUR');
        $this->assertTrue($x->equals($y));
        $this->assertEquals('1.23 EUR', (string)$x);
    }

    public function test_currency_mismatch_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $a = Price::fromCents(100, 'EUR');
        $b = Price::fromCents(100, 'USD');
        $a->add($b);
    }
}
