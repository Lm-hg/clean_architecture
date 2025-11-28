<?php
declare(strict_types=1);

namespace App\Application\UseCases\Reservation;

use App\Domain\Repositories\ReservationRepositoryInterface;

class CancelReservationUseCase
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $reservationId): void
    {
        $reservation = $this->repository->findById($reservationId);

        if (!$reservation) {
            throw new \InvalidArgumentException("Réservation non trouvée.");
        }

        // La logique métier d'annulation est dans l'entité
        $reservation->cancel();

        // On sauvegarde le nouvel état
        $this->repository->save($reservation);
    }
}

