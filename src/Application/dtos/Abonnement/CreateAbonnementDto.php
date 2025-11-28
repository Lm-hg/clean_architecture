<?php

namespace App\Application\dtos\Abonnement;

use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Pricing\Price;

class CreateAbonnementDto
{
    public string $userId;
    public string $parkingId;
    public string $type;
    /** @var TimeSlot[] */
    public array $timeSlots = [];
    public \DateTimeInterface $startDate;
    public \DateTimeInterface $endDate;
    public Price $monthlyPrice;

    public function __construct(string $userId, string $parkingId, string $type, array $timeSlots, \DateTimeInterface $startDate, \DateTimeInterface $endDate, Price $monthlyPrice)
    {
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->type = $type;
        $this->timeSlots = $timeSlots;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->monthlyPrice = $monthlyPrice;
    }
}
