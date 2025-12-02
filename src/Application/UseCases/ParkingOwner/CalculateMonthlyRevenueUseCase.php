<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\Exceptions\EntityNotFoundException;

class CalculateMonthlyRevenueUseCase
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

    public function execute(string $parkingId, string $ownerId, int $month, int $year): array
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

        // Créer les dates de début et fin du mois
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        // Récupérer toutes les réservations du mois (uniquement les confirmées et complétées)
        $reservations = $this->reservationRepository->findCompletedByParkingAndDateRange(
            $parkingId,
            $startDate,
            $endDate
        );

        // Calculer les métriques
        $totalRevenue = 0;
        $totalReservations = count($reservations);
        $revenueByDay = [];
        $reservationsByDay = [];

        // Initialiser les tableaux pour chaque jour du mois
        $daysInMonth = $startDate->format('t');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $revenueByDay[$dayKey] = 0;
            $reservationsByDay[$dayKey] = 0;
        }

        foreach ($reservations as $reservation) {
            $totalRevenue += $reservation->getTotalPrice();
            $dayKey = $reservation->getStartTime()->format('Y-m-d');
            
            if (isset($revenueByDay[$dayKey])) {
                $revenueByDay[$dayKey] += $reservation->getTotalPrice();
                $reservationsByDay[$dayKey]++;
            }
        }

        // Calculer le mois précédent pour comparaison
        $previousMonth = clone $startDate;
        $previousMonth->modify('-1 month');
        $prevStartDate = new \DateTime($previousMonth->format('Y-m-01'));
        $prevEndDate = clone $prevStartDate;
        $prevEndDate->modify('last day of this month')->setTime(23, 59, 59);

        $previousMonthReservations = $this->reservationRepository->findCompletedByParkingAndDateRange(
            $parkingId,
            $prevStartDate,
            $prevEndDate
        );

        $previousMonthRevenue = array_sum(array_map(fn($r) => $r->getTotalPrice(), $previousMonthReservations));

        return [
            'parking_id' => $parkingId,
            'parking_title' => $parking->getTitle(),
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'revenue' => [
                'total' => $totalRevenue,
                'average_per_day' => round($totalRevenue / $daysInMonth, 2),
                'average_per_reservation' => $totalReservations > 0 ? round($totalRevenue / $totalReservations, 2) : 0,
                'by_day' => $revenueByDay
            ],
            'reservations' => [
                'total' => $totalReservations,
                'average_per_day' => round($totalReservations / $daysInMonth, 2),
                'by_day' => $reservationsByDay
            ],
            'comparison' => [
                'previous_month_revenue' => $previousMonthRevenue,
                'growth' => $previousMonthRevenue > 0 ? 
                    round((($totalRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100, 2) : 0,
                'growth_amount' => $totalRevenue - $previousMonthRevenue
            ],
            'generated_at' => new \DateTime()
        ];
    }
}