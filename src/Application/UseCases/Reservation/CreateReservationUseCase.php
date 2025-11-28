<?php
declare(strict_types=1);

namespace App\Application\UseCases\Reservation;

use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;

class CreateReservationUseCase
{
    private ReservationRepositoryInterface $repository;

    public function __construct(ReservationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $userId, string $parkingId, \DateTime $start, \DateTime $end): Reservation
    {
        // 1. Validation basique des dates (déjà fait dans l'entité, mais bon de vérifier avant appel DB)
        if ($start >= $end) {
            throw new \InvalidArgumentException("La date de début doit être antérieure à la date de fin.");
        }

        // 2. Vérification de la disponibilité (Règle métier)
        // On cherche s'il existe déjà des réservations sur cet intervalle pour ce parking
        $conflictingReservations = $this->repository->findReservationsInInterval($parkingId, $start, $end);
        
        // On filtre éventuellement pour ne garder que celles qui sont confirmées ou en attente (selon la logique métier)
        // Ici on suppose que le repository retourne déjà ce qui est pertinent (ex: non annulées)
        if (count($conflictingReservations) > 0) {
            throw new \DomainException("Le parking n'est pas disponible sur ce créneau.");
        }

        // 3. Création de l'entité
        $reservation = new Reservation(
            $userId,
            $parkingId,
            $start,
            $end,
            new \DateTime(), // createdAt
            new \DateTime()  // updatedAt
        );

        // 4. Sauvegarde
        return $this->repository->save($reservation);
    }
}

