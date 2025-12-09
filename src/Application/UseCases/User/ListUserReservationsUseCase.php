<?php

namespace App\Application\UseCases\User;

use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

class ListUserReservationsUseCase
{
    private ReservationRepositoryInterface $reservationRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        ReservationRepositoryInterface $reservationRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->userRepository = $userRepository;
    }

    public function execute(string $userId, ?string $status = null, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        // Vérifier que l'utilisateur existe
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('Utilisateur non trouvé');
        }

        // Récupérer les réservations de l'utilisateur
        $reservations = $this->reservationRepository->findByUserId($userId);

        // Filtrer par statut si spécifié
        if ($status !== null) {
            $reservations = array_filter($reservations, function ($reservation) use ($status) {
                return $reservation->getStatus() === $status;
            });
        }

        // Filtrer par période si spécifiée
        if ($startDate !== null || $endDate !== null) {
            $reservations = array_filter($reservations, function ($reservation) use ($startDate, $endDate) {
                $reservationStart = $reservation->getStartTime();
                $reservationEnd = $reservation->getEndTime();

                if ($startDate !== null && $reservationEnd < $startDate) {
                    return false;
                }
                if ($endDate !== null && $reservationStart > $endDate) {
                    return false;
                }
                return true;
            });
        }

        // Trier par date décroissante (plus récent en premier)
        usort($reservations, function ($a, $b) {
            return $b->getStartTime() <=> $a->getStartTime();
        });

        // Calculer les statistiques
        $totalReservations = count($reservations);
        $reservationsByStatus = ['confirmed' => 0, 'cancelled' => 0, 'completed' => 0];
        $totalAmount = 0;

        foreach ($reservations as $reservation) {
            $status = $reservation->getStatus();
            if (isset($reservationsByStatus[$status])) {
                $reservationsByStatus[$status]++;
            }
            if ($status === 'completed') {
                $totalAmount += $reservation->getTotalPrice();
            }
        }

        return [
            'user_id' => $userId,
            'filters' => [
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'statistics' => [
                'total_reservations' => $totalReservations,
                'reservations_by_status' => $reservationsByStatus,
                'total_amount_spent' => $totalAmount
            ],
            'reservations' => $reservations
        ];
    }
}