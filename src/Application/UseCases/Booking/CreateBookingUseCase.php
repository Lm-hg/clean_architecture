<?php
namespace App\Application\UseCases\Booking;

use App\Domain\Entities\Booking;
use App\Domain\Repositories\BookingRepositoryInterface;

class CreateBookingUseCase
{
    private BookingRepositoryInterface $repository;

    public function __construct(BookingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $userId, int $parkingId, \DateTime $start, \DateTime $end): Booking
    {
        // TODO: business logic (availability check, price calculation)
        $booking = new Booking();
        // Set properties ...
        $this->repository->save($booking);
        return $booking;
    }
}
