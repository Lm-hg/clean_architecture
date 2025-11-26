<?php
namespace App\Application\UseCases\Booking;

use App\Domain\Entities\Booking;
use App\Domain\Repositories\BookingRepositoryInterface;

class GetBookingUseCase
{
    private BookingRepositoryInterface $repository;

    public function __construct(BookingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $bookingId): ?Booking
    {
        return $this->repository->findById($bookingId);
    }
}
