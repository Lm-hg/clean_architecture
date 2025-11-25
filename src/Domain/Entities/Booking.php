<?php
namespace App\Domain\Entities;

class Booking
{
    private int $id;
    private int $userId;
    private int $parkingId;
    private \DateTime $start;
    private \DateTime $end;
    private float $price;
    private string $paymentStatus; // You can change to enum later
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    // Getters and setters to come
}
