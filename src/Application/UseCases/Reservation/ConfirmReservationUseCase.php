<?php

namespace App\Application\UseCases\Reservation;

use App\Domain\Repositories\ReservationRepositoryInterface;

class ConfirmReservationUseCase
{
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(ReservationRepositoryInterface $reservationRepository)
    {
        $this->reservationRepository = $reservationRepository;
    }

    public function execute(string $reservationId): array
    {
        // Récupérer la réservation
        $reservation = $this->reservationRepository->findById($reservationId);
        
        if (!$reservation) {
            throw new \DomainException("Reservation not found");
        }

        // Confirmer la réservation
        $reservation->confirm();

        // Sauvegarder
        $this->reservationRepository->save($reservation);

        return [
            'id' => $reservation->getId(),
            'status' => $reservation->getStatus(),
            'userId' => $reservation->getUserId(),
            'parkingId' => $reservation->getParkingId(),
            'startTime' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
            'endTime' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
            'totalPrice' => $reservation->getTotalPrice(),
            'createdAt' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $reservation->getUpdatedAt()->format('Y-m-d H:i:s')
        ];
    }
}
