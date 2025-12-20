<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Price\Price;

class Reservation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';

    private ?string $id;
    private string $userId;
    private string $parkingId;
    private \DateTime $startTime;
    private \DateTime $endTime;
    private ?Price $price;
    private string $status;
    private bool $isPaid;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        string $userId,
        string $parkingId,
        \DateTime $startTime,
        \DateTime $endTime,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $id = null
    ) {
        $this->validateUserId($userId);
        $this->validateParkingId($parkingId);
        $this->validateTimeRange($startTime, $endTime);

        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->price = null;
        $this->status = self::STATUS_CONFIRMED;
        $this->isPaid = false;
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

    private function validateTimeRange(\DateTime $startTime, \DateTime $endTime): void
    {
        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException("La date de debut doit etre anterieure a la date de fin");
        }

        $duration = $endTime->getTimestamp() - $startTime->getTimestamp();
        $minDuration = 900; // 15 minutes
        if ($duration < $minDuration) {
            throw new \InvalidArgumentException("Minimum duration is 15 minutes");
        }

        $maxDuration = 86400 * 30; // 30 days max for reservation
        if ($duration > $maxDuration) {
            throw new \InvalidArgumentException("Maximum duration is 30 days");
        }
    }

    private function validateStatus(string $status): void
    {
        $validStatuses = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_ACTIVE, self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_NO_SHOW];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
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

    public function getStartTime(): \DateTime
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTime
    {
        return $this->endTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStartTimestamp(): int
    {
        return $this->startTime->getTimestamp();
    }

    public function getEndTimestamp(): int
    {
        return $this->endTime->getTimestamp();
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function getTotalPrice(): float
    {
        return $this->price ? $this->price->getAmount() : 0.0;
    }

    public function setPrice(Price $price): void
    {
        $this->price = $price;
        $this->updatedAt = new \DateTime();
    }

    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }

    public function markAsPaid(): void
    {
        $this->isPaid = true;
        $this->updatedAt = new \DateTime();
    }

    public function confirm(): void
    {
        $this->validateStatus($this->status);
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException("Only pending reservations can be confirmed");
        }
        $this->status = self::STATUS_CONFIRMED;
        $this->updatedAt = new \DateTime();
    }

    public function activate(): void
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            throw new \DomainException("Only confirmed reservations can be activated");
        }
        $this->status = self::STATUS_ACTIVE;
        $this->updatedAt = new \DateTime();
    }

    public function cancel(): void
    {
        if ($this->status === self::STATUS_CANCELLED || $this->status === self::STATUS_COMPLETED) {
            throw new \DomainException("Cannot cancel reservation with status: {$this->status}");
        }
        $this->status = self::STATUS_CANCELLED;
        $this->updatedAt = new \DateTime();
    }

    public function complete(): void
    {
        if ($this->status !== self::STATUS_CONFIRMED && $this->status !== self::STATUS_ACTIVE) {
            throw new \DomainException("Only confirmed or active reservations can be completed");
        }
        $this->status = self::STATUS_COMPLETED;
        $this->updatedAt = new \DateTime();
    }

    public function markAsNoShow(): void
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            throw new \DomainException("Only confirmed reservations can be marked as no-show");
        }
        $this->status = self::STATUS_NO_SHOW;
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

    public function getDuration(): int
    {
        return $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
    }

    public function getDurationInQuarters(): int
    {
        return (int)ceil($this->getDuration() / 900);
    }

    public function getDurationInMinutes(): int
    {
        return (int)($this->getDuration() / 60);
    }

    public function isActiveAt(\DateTime $dateTime): bool
    {
        return $dateTime >= $this->startTime && $dateTime < $this->endTime;
    }

    public function hasStarted(\DateTime $currentDateTime): bool
    {
        return $currentDateTime >= $this->startTime;
    }

    public function hasEnded(\DateTime $currentDateTime): bool
    {
        return $currentDateTime >= $this->endTime;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function overlaps(Reservation $other): bool
    {
        return !($this->endTime <= $other->startTime || $this->startTime >= $other->endTime);
    }

    public function belongsToUser(string $userId): bool
    {
        return $this->userId === $userId;
    }

    public function isForParking(string $parkingId): bool
    {
        return $this->parkingId === $parkingId;
    }

    public static function reconstitute(
        string $id,
        string $userId,
        string $parkingId,
        \DateTime $startTime,
        \DateTime $endTime,
        string $status,
        ?Price $price,
        bool $isPaid,
        \DateTime $createdAt,
        \DateTime $updatedAt
    ): self {
        $reservation = new self(
            $userId,
            $parkingId,
            $startTime,
            $endTime,
            $createdAt,
            $updatedAt,
            $id
        );
        
        $reservation->status = $status;
        $reservation->price = $price;
        $reservation->isPaid = $isPaid;
        
        return $reservation;
    }
}
