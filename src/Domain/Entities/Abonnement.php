<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Pricing\Price;
use App\Domain\ValueObjects\TimeSlot;

class Abonnement
{
    // Types prédéfinis (peuvent être utilisés comme référence)
    public const TYPE_TOTAL = 'total';
    public const TYPE_WEEKEND = 'weekend';
    public const TYPE_SOIR = 'soir';
    public const TYPE_SPECIFIQUE = 'specifique';
    public const TYPE_HEBDOMADAIRE = 'hebdomadaire';
    public const TYPE_MENSUEL = 'mensuel';
    public const TYPE_TRIMESTRIEL = 'trimestriel';

    public const MIN_DURATION_DAYS = 1;
    public const MAX_DURATION_DAYS = 365;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    private ?string $id;
    private string $userId;
    private string $parkingId;
    private string $type;
    private array $timeSlots;
    private \DateTime $startDate;
    private \DateTime $endDate;
    private Price $monthlyPrice;
    private string $status;
    private bool $isPaid;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(
        string $userId,
        string $parkingId,
        string $type,
        array $timeSlots,
        \DateTime $startDate,
        \DateTime $endDate,
        Price $monthlyPrice,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        ?string $id = null
    ) {
        $this->validateUserId($userId);
        $this->validateParkingId($parkingId);
        $this->validateType($type);
        // Set type early so validateTimeSlots can allow empty for TYPE_TOTAL
        $this->type = $type;
        $this->validateTimeSlots($timeSlots);
        $this->validateDateRange($startDate, $endDate);

        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->type = $type;
        $this->timeSlots = $timeSlots;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->monthlyPrice = $monthlyPrice;
        $this->status = self::STATUS_ACTIVE;
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

    private function validateType(string $type): void
    {
        // Accepter n'importe quel type non vide
        // Les propriétaires de parking peuvent définir leurs propres types d'abonnement
        if (empty(trim($type))) {
            throw new \InvalidArgumentException("Subscription type cannot be empty");
        }
    }

    private function validateTimeSlots(array $timeSlots): void
    {
        // Les time slots sont optionnels - si vide, accès illimité
        // Sinon, chaque élément doit être un TimeSlot valide
        foreach ($timeSlots as $slot) {
            if (!($slot instanceof TimeSlot)) {
                throw new \InvalidArgumentException("All time slots must be TimeSlot instances");
            }
        }
    }

    private function validateDateRange(\DateTime $startDate, \DateTime $endDate): void
    {
        if ($startDate >= $endDate) {
            throw new \InvalidArgumentException("Start date must be before end date");
        }

        $interval = $startDate->diff($endDate);
        $durationInDays = $interval->days;

        if ($durationInDays < self::MIN_DURATION_DAYS) {
            throw new \InvalidArgumentException("Minimum subscription duration is 1 day");
        }

        if ($durationInDays > self::MAX_DURATION_DAYS) {
            throw new \InvalidArgumentException("Maximum subscription duration is 365 days");
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTimeSlots(): array
    {
        return $this->timeSlots;
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTime
    {
        return $this->endDate;
    }

    public function getStartTimestamp(): int
    {
        return $this->startDate->getTimestamp();
    }

    public function getEndTimestamp(): int
    {
        return $this->endDate->getTimestamp();
    }

    public function getMonthlyPrice(): Price
    {
        return $this->monthlyPrice;
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function isValidAt(\DateTime $dateTime): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               $dateTime >= $this->startDate && 
               $dateTime <= $this->endDate;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function expire(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \DomainException("Only active subscriptions can expire");
        }
        $this->status = self::STATUS_EXPIRED;
        $this->updatedAt = new \DateTime();
    }

    public function cancel(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \DomainException("Only active subscriptions can be cancelled");
        }
        $this->status = self::STATUS_CANCELLED;
        $this->updatedAt = new \DateTime();
    }

    public function coversTimeSlot(int $dayOfWeek, int $timeOfDay): bool
    {
        foreach ($this->timeSlots as $slot) {
            if ($slot->isActiveAt($dayOfWeek, $timeOfDay)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'abonnement couvre un DateTime donné (en tenant compte des timeSlots hebdomadaires)
     */
    public function coversDateTime(\DateTimeInterface $dateTime): bool
    {
        if ($this->isTotalAccess()) {
            return true;
        }

        foreach ($this->timeSlots as $slot) {
            if (!($slot instanceof \App\Domain\ValueObjects\TimeSlot)) {
                continue;
            }
            if ($slot->coversDateTime($dateTime)) {
                return true;
            }
        }

        return false;
    }

    public function getDurationInMonths(): int
    {
        $interval = $this->startDate->diff($this->endDate);
        return $interval->m + ($interval->y * 12);
    }

    public function belongsToUser(string $userId): bool
    {
        return $this->userId === $userId;
    }

    public function isForParking(string $parkingId): bool
    {
        return $this->parkingId === $parkingId;
    }

    public function isTotalAccess(): bool
    {
        return $this->type === self::TYPE_TOTAL;
    }

    public function getRemainingDays(\DateTime $currentDateTime): int
    {
        if ($currentDateTime > $this->endDate) {
            return 0;
        }
        $interval = $currentDateTime->diff($this->endDate);
        return $interval->days;
    }

    public function getTotalPrice(): float
    {
        $months = $this->getDurationInMonths();
        return $this->monthlyPrice->getAmount() * $months;
    }

    public function hasStarted(\DateTime $currentDateTime): bool
    {
        return $currentDateTime >= $this->startDate;
    }

    public function hasEnded(\DateTime $currentDateTime): bool
    {
        return $currentDateTime > $this->endDate;
    }

    /**
     * Reconstitue un Abonnement depuis la base de données sans déclencher les validations du constructeur
     */
    public static function reconstitute(
        string $userId,
        string $parkingId,
        string $type,
        array $timeSlots,
        \DateTime $startDate,
        \DateTime $endDate,
        Price $monthlyPrice,
        \DateTime $createdAt,
        \DateTime $updatedAt,
        string $status,
        bool $isPaid,
        ?string $id = null
    ): self {
        $abonnement = new self(
            $userId,
            $parkingId,
            $type,
            $timeSlots,
            $startDate,
            $endDate,
            $monthlyPrice,
            $createdAt,
            $updatedAt,
            $id
        );
        
        // Appliquer les états depuis la base de données
        $abonnement->status = $status;
        $abonnement->isPaid = $isPaid;
        
        return $abonnement;
    }
}
