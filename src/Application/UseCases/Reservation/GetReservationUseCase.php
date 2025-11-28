<?php
declare(strict_types=1);

namespace App\Application\UseCases\Reservation;

use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;

class GetReservationUseCase
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $reservationId): ?Reservation
    {
        return $this->repository->findById($reservationId);
    }
}

