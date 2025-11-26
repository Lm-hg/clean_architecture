<?php
namespace App\Application\UseCases;

use App\Domain\Entities\Booking;
use App\Domain\Repositories\BookingRepositoryInterface;

class ListBookingsForUserUseCase
{
    private BookingRepositoryInterface $repository;

    public function __construct(BookingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /** @return Booking[] */
    public function execute(int $userId): array
    {
        return $this->repository->findByUserId($userId);
    }
}
