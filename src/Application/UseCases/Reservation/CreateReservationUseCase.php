<?php
declare(strict_types=1);

namespace App\Application\UseCases\Reservation;

use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;

class CreateReservationUseCase
{
    private ReservationRepositoryInterface $repository;
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(
        ReservationRepositoryInterface $repository,
        ParkingRepositoryInterface $parkingRepository
    ) {
        $this->repository = $repository;
        $this->parkingRepository = $parkingRepository;
    }

    public function execute(string $userId, string $parkingId, \DateTime $start, \DateTime $end): Reservation
    {
        // 1. Validation basique des dates
        if ($start >= $end) {
            throw new \InvalidArgumentException("La date de début doit être antérieure à la date de fin.");
        }

        // 2. Vérifier la durée minimale (15 minutes)
        $durationMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        if ($durationMinutes < 15) {
            throw new \InvalidArgumentException("La durée minimale de réservation est de 15 minutes.");
        }

        // 3. Récupérer le parking et vérifier la disponibilité
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new \DomainException("Parking non trouvé.");
        }

        // 4. Vérifier qu'il y a des places disponibles
        if ($parking->getAvailableSpots() <= 0) {
            throw new \DomainException("Aucune place disponible dans ce parking.");
        }

        // 5. Vérification des conflits de réservations existantes
        $conflictingReservations = $this->repository->findReservationsInInterval($parkingId, $start, $end);
        if (count($conflictingReservations) > 0) {
            throw new \DomainException("Le parking n'est pas disponible sur ce créneau.");
        }

        // 6. Création de l'entité
        $reservation = new Reservation(
            $userId,
            $parkingId,
            $start,
            $end,
            new \DateTime(),
            new \DateTime()
        );

        // 7. Sauvegarder la réservation
        $savedReservation = $this->repository->save($reservation);

        // 8. Décrémenter le nombre de places disponibles
        $parking->decrementAvailableSpots();
        $this->parkingRepository->save($parking);

        return $savedReservation;
    }
}

