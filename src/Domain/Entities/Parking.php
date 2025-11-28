<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Pricing\TarifCollection;

class Parking
{
    private ?string $id;
    private string $ownerId;
    private string $title;
    private ?string $description;
    private Address $address;
    private GPSCoordinates $coordinates;
    private int $totalSpots;
    private int $availableSpots;
    private TarifCollection $tarifs;
    private OpeningHours $openingHours;
    private bool $isAvailable;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        string $ownerId,
        string $title,
        Address $address,
        GPSCoordinates $coordinates,
        int $totalSpots,
        TarifCollection $tarifs,
        OpeningHours $openingHours,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $description = null,
        ?string $id = null
    ) {
        $this->validateOwnerId($ownerId);
        $this->validateTitle($title);
        $this->validateTotalSpots($totalSpots);

        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->coordinates = $coordinates;
        $this->totalSpots = $totalSpots;
        $this->availableSpots = $totalSpots; // Initialement toutes les places sont disponibles
        $this->tarifs = $tarifs;
        $this->openingHours = $openingHours;
        $this->isAvailable = true;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    private function validateOwnerId(string $ownerId): void
    {
        if (empty(trim($ownerId))) {
            throw new \InvalidArgumentException("Owner ID cannot be empty");
        }
    }

    private function validateTitle(string $title): void
    {
        if (empty(trim($title))) {
            throw new \InvalidArgumentException("Title cannot be empty");
        }
        if (strlen($title) > 200) {
            throw new \InvalidArgumentException("Title cannot exceed 200 characters");
        }
    }

    private function validateTotalSpots(int $totalSpots): void
    {
        if ($totalSpots <= 0) {
            throw new \InvalidArgumentException("Total spots must be positive");
        }
        if ($totalSpots > 10000) {
            throw new \InvalidArgumentException("Total spots cannot exceed 10000");
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getCoordinates(): GPSCoordinates
    {
        return $this->coordinates;
    }

    public function getTotalSpots(): int
    {
        return $this->totalSpots;
    }

    public function getAvailableSpots(): int
    {
        return $this->availableSpots;
    }

    public function updateTotalSpots(int $totalSpots): void
    {
        $this->validateTotalSpots($totalSpots);
        $this->totalSpots = $totalSpots;
        $this->updatedAt = new \DateTime();
    }

    public function decrementAvailableSpots(int $count = 1): void
    {
        if ($this->availableSpots < $count) {
            throw new \DomainException("Not enough available spots");
        }
        $this->availableSpots -= $count;
    }

    public function incrementAvailableSpots(int $count = 1): void
    {
        if (($this->availableSpots + $count) > $this->totalSpots) {
            throw new \DomainException("Cannot exceed total spots");
        }
        $this->availableSpots += $count;
    }

    public function getTarifs(): TarifCollection
    {
        return $this->tarifs;
    }

    public function updateTarifs(TarifCollection $tarifs): void
    {
        $this->tarifs = $tarifs;
        $this->updatedAt = new \DateTime();
    }

    public function hasAvailableSpots(): bool
    {
        return $this->availableSpots > 0;
    }

    public function canAccommodate(int $requestedSpots): bool
    {
        return $this->availableSpots >= $requestedSpots;
    }

    public function isOpenAt(\DateTime $dateTime): bool
    {
        return $this->openingHours->isOpenAt($dateTime);
    }

    public function isOpenDuring(\DateTime $startDateTime, \DateTime $endDateTime): bool
    {
        return $this->openingHours->isOpenDuring($startDateTime, $endDateTime);
    }

    public function belongsToOwner(string $ownerId): bool
    {
        return $this->ownerId === $ownerId;
    }

    public function getOpeningHours(): OpeningHours
    {
        return $this->openingHours;
    }

    public function updateOpeningHours(OpeningHours $openingHours): void
    {
        $this->openingHours = $openingHours;
        $this->updatedAt = new \DateTime();
    }

    public function getIsAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
        $this->updatedAt = new \DateTime();
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function updateDetails(string $title, ?string $description, Address $address): void
    {
        $this->validateTitle($title);
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->updatedAt = new \DateTime();
    }
}
