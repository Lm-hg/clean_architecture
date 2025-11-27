<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Price\Price;

class Stationnement
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENALIZED = 'penalized';
    public const PENALTY_AMOUNT = 20.0;

    private ?string $id;
    private string $userId;
    private string $parkingId;
    private ?string $reservationId;
    private ?string $abonnementId;
    private \DateTime $entryTime;
    private ?\DateTime $exitTime;
    private ?Price $price;
    private string $status;
    private bool $hasPenalty;
    private float $penaltyAmount;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        string $userId,
        string $parkingId,
        \DateTime $entryTime,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $reservationId = null,
        ?string $abonnementId = null,
        ?string $id = null
    ) {
        $this->validateUserId($userId);
        $this->validateParkingId($parkingId);

        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->reservationId = $reservationId;
        $this->abonnementId = $abonnementId;
        $this->entryTime = $entryTime;
        $this->exitTime = null;
        $this->price = null;
        $this->status = self::STATUS_ACTIVE;
        $this->hasPenalty = false;
        $this->penaltyAmount = 0.0;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    private function validateUserId(string $userId): void
    {
        if (empty(trim($userId))) {
            throw new \InvalidArgumentException("User ID cannot be empty");
        }
    }

    private function validateParkingId(string $parkingId): void
    {
        if (empty(trim($parkingId))) {
            throw new \InvalidArgumentException("Parking ID cannot be empty");
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getParkingId(): string
    {
        return $this->parkingId;
    }

    public function getReservationId(): ?string
    {
        return $this->reservationId;
    }

    public function getAbonnementId(): ?string
    {
        return $this->abonnementId;
    }

    public function getEntryTime(): \DateTime
    {
        return $this->entryTime;
    }

    public function getExitTime(): ?\DateTime
    {
        return $this->exitTime;
    }

    public function getEntryTimestamp(): int
    {
        return $this->entryTime->getTimestamp();
    }

    public function getExitTimestamp(): ?int
    {
        return $this->exitTime?->getTimestamp();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function setPrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = new \DateTime();
    }

    public function getHasPenalty(): bool
    {
        return $this->hasPenalty;
    }

    public function getPenaltyAmount(): float
    {
        return $this->penaltyAmount;
    }

    public function applyPenalty(float $amount = self::PENALTY_AMOUNT): void
    {
        if ($this->hasPenalty) {
            throw new \DomainException("Penalty already applied");
        }
        $this->hasPenalty = true;
        $this->penaltyAmount = $amount;
        $this->status = self::STATUS_PENALIZED;
        $this->updatedAt = new \DateTime();
    }

    public function belongsToUser(string $userId): bool
    {
        return $this->userId === $userId;
    }

    public function isForParking(string $parkingId): bool
    {
        return $this->parkingId === $parkingId;
    }

    public function hasReservation(): bool
    {
        return $this->reservationId !== null;
    }

    public function hasAbonnement(): bool
    {
        return $this->abonnementId !== null;
    }

    public function isLinkedToReservation(string $reservationId): bool
    {
        return $this->reservationId === $reservationId;
    }

    public function isLinkedToAbonnement(string $abonnementId): bool
    {
        return $this->abonnementId === $abonnementId;
    }

    public function exceedsReservation(\DateTime $reservationEndTime): bool
    {
        if ($this->exitTime === null) {
            return false;
        }
        return $this->exitTime > $reservationEndTime;
    }

    public function exceedsAbonnementSlot(\DateTime $abonnementEndTime): bool
    {
        if ($this->exitTime === null) {
            return false;
        }
        return $this->exitTime > $abonnementEndTime;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPenalized(): bool
    {
        return $this->status === self::STATUS_PENALIZED;
    }

    public function isActiveAt(\DateTime $dateTime): bool
    {
        if ($this->exitTime === null) {
            return $dateTime >= $this->entryTime;
        }
        return $dateTime >= $this->entryTime && $dateTime < $this->exitTime;
    }

    public function getDuration(): ?int
    {
        if ($this->exitTime === null) {
            return null;
        }
        return $this->exitTime->getTimestamp() - $this->entryTime->getTimestamp();
    }

    public function getDurationInMinutes(): ?int
    {
        $duration = $this->getDuration();
        return $duration !== null ? (int)($duration / 60) : null;
    }

    public function exit(\DateTime $exitTime): void
    {
        if ($this->exitTime !== null) {
            throw new \DomainException("Stationnement already ended");
        }
        if ($exitTime <= $this->entryTime) {
            throw new \InvalidArgumentException("Exit time must be after entry time");
        }
        $this->exitTime = $exitTime;
        $this->status = $this->hasPenalty ? self::STATUS_PENALIZED : self::STATUS_COMPLETED;
        $this->updatedAt = new \DateTime();
    }

    public function getTotalAmount(): float
    {
        if ($this->price === null) {
            return 0.0;
        }
        return $this->price->getAmount() + $this->penaltyAmount;
    }
}
