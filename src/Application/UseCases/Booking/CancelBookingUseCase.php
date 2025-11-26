<?php
namespace App\Application\UseCases\Booking;

use App\Domain\Entities\Booking;
use App\Domain\Repositories\BookingRepositoryInterface;

class CancelBookingUseCase
{
    private BookingRepositoryInterface $repository;

    public function __construct(BookingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $bookingId): void
    {
        $booking = $this->repository->findById($bookingId);
        if ($booking === null) {
            throw new \Exception("Booking not found.");
        }
        // TODO: business logic (cancellation rules)
        $this->repository->cancel($booking);
    }
}
