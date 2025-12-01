<?php

namespace App\Application\dtos\parking;

use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;

class CreateParkingDto
{
    public string $ownerId;
    public string $title;
    public ?string $description;
    public Address $address;
    public GPSCoordinates $coordinates;
    public int $totalSpots;
    public TarifCollection $tarifs;
    public OpeningHours $openingHours;

    public function __construct(
        string $ownerId,
        string $title,
        Address $address,
        GPSCoordinates $coordinates,
        int $totalSpots,
        TarifCollection $tarifs,
        OpeningHours $openingHours,
        ?string $description = null
    ) {
        $this->ownerId = $ownerId;
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->coordinates = $coordinates;
        $this->totalSpots = $totalSpots;
        $this->tarifs = $tarifs;
        $this->openingHours = $openingHours;
    }
}