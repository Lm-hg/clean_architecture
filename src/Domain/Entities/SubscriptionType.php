<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Pricing\Price;

class SubscriptionType
{
    private ?string $id;
    private string $parkingId;
    private string $name;
    private ?string $description;
    private array $benefits; // Array of strings
    private Price $price;
    private int $durationDays;
    private array $timeSlots; // Array of TimeSlot objects
    private bool $isActive;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        string $parkingId,
        string $name,
        Price $price,
        int $durationDays,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $description = null,
        array $benefits = [],
        array $timeSlots = [],
        bool $isActive = true,
        ?string $id = null
    ) {
        $this->validateParkingId($parkingId);
        $this->validateName($name);
        $this->validateDurationDays($durationDays);
        $this->validateBenefits($benefits);

        $this->id = $id;
        $this->parkingId = $parkingId;
        $this->name = $name;
        $this->description = $description;
        $this->benefits = $benefits;
        $this->price = $price;
        $this->durationDays = $durationDays;
        $this->timeSlots = $timeSlots;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    private function validateParkingId(string $parkingId): void
    {
        if (empty(trim($parkingId))) {
            throw new \InvalidArgumentException("Parking ID cannot be empty");
        }
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException("Subscription type name cannot be empty");
        }
        if (strlen($name) > 100) {
            throw new \InvalidArgumentException("Subscription type name cannot exceed 100 characters");
        }
    }

    private function validateDurationDays(int $durationDays): void
    {
        if ($durationDays <= 0) {
            throw new \InvalidArgumentException("Duration must be positive");
        }
    }

    private function validateBenefits(array $benefits): void
    {
        foreach ($benefits as $benefit) {
            if (!is_string($benefit)) {
                throw new \InvalidArgumentException("All benefits must be strings");
            }
        }
    }

    // Getters
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getParkingId(): string
    {
        return $this->parkingId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBenefits(): array
    {
        return $this->benefits;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function getTimeSlots(): array
    {
        return $this->timeSlots;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // Setters
    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
    }

    public function setBenefits(array $benefits): void
    {
        $this->validateBenefits($benefits);
        $this->benefits = $benefits;
        $this->updatedAt = new \DateTime();
    }

    public function setPrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = new \DateTime();
    }

    public function setDurationDays(int $durationDays): void
    {
        $this->validateDurationDays($durationDays);
        $this->durationDays = $durationDays;
        $this->updatedAt = new \DateTime();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTime();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parkingId' => $this->parkingId,
            'name' => $this->name,
            'description' => $this->description,
            'benefits' => $this->benefits,
            'price' => $this->price->getAmount(),
            'durationDays' => $this->durationDays,
            'timeSlots' => array_map(fn($slot) => $slot->toArray(), $this->timeSlots),
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
