<?php

namespace App\Application\UseCases\Stationnement;

use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Entities\Stationnement;

class StartStationnementFromReservationUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->reservationRepository = $reservationRepository;
    }

    public function execute(string $reservationId, string $userId): array
    {
        error_log("StartStationnement: reservationId=$reservationId, userId=$userId");
        
        // Récupérer la réservation
        $reservation = $this->reservationRepository->findById($reservationId);
        
        if (!$reservation) {
            error_log("StartStationnement: Reservation not found");
            throw new \DomainException("Reservation not found");
        }

        error_log("StartStationnement: Reservation status=" . $reservation->getStatus());
        
        // Vérifier que la réservation appartient à l'utilisateur
        if (!$reservation->belongsToUser($userId)) {
            error_log("StartStationnement: Reservation does not belong to user");
            throw new \DomainException("This reservation does not belong to you");
        }

        // Vérifier que la réservation est confirmée
        if (!$reservation->isConfirmed()) {
            error_log("StartStationnement: Reservation not confirmed, status=" . $reservation->getStatus());
            throw new \DomainException("Reservation must be confirmed before starting a stationnement");
        }

        // Vérifier que la réservation n'a pas déjà un stationnement actif
        $existingStationnements = $this->stationnementRepository->findByReservationId($reservationId);
        foreach ($existingStationnements as $stat) {
            if ($stat->isActive()) {
                throw new \DomainException("A stationnement is already active for this reservation");
            }
        }

        // Créer le stationnement
        $now = new \DateTime();
        $stationnement = new Stationnement(
            $userId,
            $reservation->getParkingId(),
            $now,
            $now,
            $now,
            $reservationId,
            null
        );

        // Sauvegarder le stationnement
        $savedStationnement = $this->stationnementRepository->save($stationnement);

        // Changer le statut de la réservation à "active"
        $reservation->activate();
        $this->reservationRepository->save($reservation);

        return [
            'id' => $savedStationnement->getId(),
            'userId' => $savedStationnement->getUserId(),
            'parkingId' => $savedStationnement->getParkingId(),
            'reservationId' => $savedStationnement->getReservationId(),
            'entryTime' => $savedStationnement->getEntryTime()->format('Y-m-d H:i:s'),
            'status' => $savedStationnement->getStatus(),
            'createdAt' => $savedStationnement->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
}
