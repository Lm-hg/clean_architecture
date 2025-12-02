<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\Exceptions\EntityNotFoundException;

class ListParkingReservationsUseCase
{
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
    }

    public function execute(string $parkingId, string $ownerId, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        // Récupérer le parking
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new EntityNotFoundException('Parking non trouvé');
        }

        // Vérifier que le propriétaire est bien le propriétaire du parking
        if ($parking->getOwnerId() !== $ownerId) {
            throw new UnauthorizedAccessException('Vous n\'avez pas accès à ce parking');
        }

        // Définir les dates par défaut si non fournies
        if ($startDate === null) {
            $startDate = new \DateTime('today');
        }
        if ($endDate === null) {
            $endDate = new \DateTime('+30 days');
        }

        // Récupérer les réservations pour le parking dans la période donnée
        $reservations = $this->reservationRepository->findByParkingAndDateRange(
            $parkingId,
            $startDate,
            $endDate
        );

        // Calculer les statistiques
        $totalReservations = count($reservations);
        $totalRevenue = 0;
        $reservationsByStatus = ['confirmed' => 0, 'cancelled' => 0, 'completed' => 0];

        foreach ($reservations as $reservation) {
            $totalRevenue += $reservation->getTotalPrice();
            $status = $reservation->getStatus();
            if (isset($reservationsByStatus[$status])) {
                $reservationsByStatus[$status]++;
            }
        }

        return [
            'parking_id' => $parkingId,
            'parking_title' => $parking->getTitle(),
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'statistics' => [
                'total_reservations' => $totalReservations,
                'total_revenue' => $totalRevenue,
                'reservations_by_status' => $reservationsByStatus,
                'average_revenue_per_reservation' => $totalReservations > 0 ? round($totalRevenue / $totalReservations, 2) : 0
            ],
            'reservations' => $reservations
        ];
    }
}