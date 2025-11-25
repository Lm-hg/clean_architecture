<?php
namespace App\Domain\Repositories;

use App\Domain\Entities\Booking;

interface BookingRepositoryInterface
{
    public function save(Booking $booking): void;
    public function findById(int $id): ?Booking;
    public function findByUserId(int $userId): array;
    public function cancel(Booking $booking): void;
}
