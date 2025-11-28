<?php

namespace App\Domain\ValueObjects\Pricing;

use App\Domain\ValueObjects\Price\Price;
use App\Domain\ValueObjects\TimeSlot;

class Tarif
{
    private string $label;
    private Price $price;
    private ?TimeSlot $slot;

    public function __construct(string $label, Price $price, ?TimeSlot $slot = null)
    {
        if (empty(trim($label))) {
            throw new \InvalidArgumentException('Label cannot be empty');
        }
        $this->label = $label;
        $this->price = $price;
        $this->slot = $slot;
    }

    public function appliesAt(\DateTime $dateTime): bool
    {
        if ($this->slot === null) {
            return true;
        }
        $day = (int)$dateTime->format('N');
        $minute = (int)$dateTime->format('G') * 60 + (int)$dateTime->format('i');
        return $this->slot->isActiveAt($day, $minute);
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
