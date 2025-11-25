<?php
namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\Booking;
use App\Domain\Repositories\BookingRepositoryInterface;

class BookingRepository implements BookingRepositoryInterface
{
    public function save(Booking $booking): void
    {
        // TODO: implement save logic (insert/update) using ORM or DBAL
    }

    public function findById(int $id): ?Booking
    {
        // TODO: implement retrieval logic
        return null;
    }

    public function findByUserId(int $userId): array
    {
        // TODO: implement list logic
        return [];
    }

    public function cancel(Booking $booking): void
    {
        // TODO: implement cancellation logic
    }
}
