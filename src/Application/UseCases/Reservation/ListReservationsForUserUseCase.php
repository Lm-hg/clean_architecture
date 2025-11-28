<?php
declare(strict_types=1);

namespace App\Application\UseCases\Reservation;

use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;

class ListReservationsForUserUseCase
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /** 
     * @return Reservation[] 
     */
    public function execute(string $userId): array
    {
        return $this->repository->findByUserId($userId);
    }
}

