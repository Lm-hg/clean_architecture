<?php

namespace App\Application\dtos\parking;

use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;

class UpdateParkingDto
{
    public ?string $title;
    public ?string $description;
    public ?Address $address;
    public ?GPSCoordinates $coordinates;
    public ?int $totalSpots;
    public ?TarifCollection $tarifs;
    public ?OpeningHours $openingHours;
    public ?bool $isAvailable;

    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?Address $address = null,
        ?GPSCoordinates $coordinates = null,
        ?int $totalSpots = null,
        ?TarifCollection $tarifs = null,
        ?OpeningHours $openingHours = null,
        ?bool $isAvailable = null
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->coordinates = $coordinates;
        $this->totalSpots = $totalSpots;
        $this->tarifs = $tarifs;
        $this->openingHours = $openingHours;
        $this->isAvailable = $isAvailable;
    }
}