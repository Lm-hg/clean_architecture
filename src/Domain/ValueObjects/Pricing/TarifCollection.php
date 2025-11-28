<?php

namespace App\Domain\ValueObjects\Pricing;

use App\Domain\ValueObjects\Price\Price;

class TarifCollection
{
    /** @var Tarif[] */
    private array $tarifs = [];

    /** @param Tarif[] $tarifs */
    public function __construct(array $tarifs = [])
    {
        foreach ($tarifs as $t) {
            if (!($t instanceof Tarif)) {
                throw new \InvalidArgumentException('All elements must be Tarif instances');
            }
        }
        $this->tarifs = $tarifs;
    }

    public function add(Tarif $tarif): void
    {
        $this->tarifs[] = $tarif;
    }

    public function getApplicablePrice(\DateTime $dateTime): ?Price
    {
        foreach ($this->tarifs as $tarif) {
            if ($tarif->appliesAt($dateTime)) {
                return $tarif->getPrice();
            }
        }
        return null;
    }

    /** @return Tarif[] */
    public function all(): array
    {
        return $this->tarifs;
    }
}
