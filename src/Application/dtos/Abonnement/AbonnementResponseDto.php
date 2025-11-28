<?php

namespace App\Application\dtos\Abonnement;

use App\Domain\ValueObjects\Pricing\Price;

class AbonnementResponseDto
{
    public string $id;
    public string $userId;
    public string $parkingId;
    public string $type;
    public string $startDate; // ISO
    public string $endDate; // ISO
    public Price $monthlyPrice;

    public function __construct(string $id, string $userId, string $parkingId, string $type, string $startDate, string $endDate, Price $monthlyPrice)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->parkingId = $parkingId;
        $this->type = $type;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->monthlyPrice = $monthlyPrice;
    }
}
