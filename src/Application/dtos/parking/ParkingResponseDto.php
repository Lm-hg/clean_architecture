<?php

namespace App\Application\dtos\parking;

use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;

class ParkingResponseDto
{
    public string $id;
    public string $ownerId;
    public string $title;
    public ?string $description;
    public Address $address;
    public GPSCoordinates $coordinates;
    public int $totalSpots;
    public int $availableSpots;
    public TarifCollection $tarifs;
    public OpeningHours $openingHours;
    public bool $isAvailable;
    public \DateTime $createdAt;
    public \DateTime $updatedAt;

    public function __construct(
        string $id,
        string $ownerId,
        string $title,
        ?string $description,
        Address $address,
        GPSCoordinates $coordinates,
        int $totalSpots,
        int $availableSpots,
        TarifCollection $tarifs,
        OpeningHours $openingHours,
        bool $isAvailable,
        \DateTime $createdAt,
        \DateTime $updatedAt
    ) {
        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->coordinates = $coordinates;
        $this->totalSpots = $totalSpots;
        $this->availableSpots = $availableSpots;
        $this->tarifs = $tarifs;
        $this->openingHours = $openingHours;
        $this->isAvailable = $isAvailable;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}