<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedAccessException;

class ListParkingStatonnementsUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ParkingRepositoryInterface $parkingRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->parkingRepository = $parkingRepository;
    }

    public function execute(string $parkingId, string $ownerId, ?\DateTime $startDate = null, ?\DateTime $endDate = null, ?bool $activeOnly = false): array
    {
        // Vérifier que le parking existe et appartient au propriétaire
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new EntityNotFoundException('Parking non trouvé');
        }

        if ($parking->getOwnerId() !== $ownerId) {
            throw new UnauthorizedAccessException('Vous n\'avez pas accès à ce parking');
        }

        // Récupérer tous les stationnements du parking
        $stationnements = $this->stationnementRepository->findByParkingId($parkingId);

        // Filtrer par statut actif si demandé
        if ($activeOnly) {
            $stationnements = array_filter($stationnements, function ($stationnement) {
                return $stationnement->getExitTime() === null; // Stationnement en cours
            });
        }

        // Filtrer par période si spécifiée
        if ($startDate !== null || $endDate !== null) {
            $stationnements = array_filter($stationnements, function ($stationnement) use ($startDate, $endDate) {
                $entryTime = $stationnement->getEntryTime();

                if ($startDate !== null && $entryTime < $startDate) {
                    return false;
                }
                if ($endDate !== null && $entryTime > $endDate) {
                    return false;
                }
                return true;
            });
        }

        // Trier par date d'entrée décroissante
        usort($stationnements, function ($a, $b) {
            return $b->getEntryTime() <=> $a->getEntryTime();
        });

        // Calculer les statistiques
        $totalStationnements = count($stationnements);
        $activeStationnements = 0;
        $completedStationnements = 0;
        $totalRevenue = 0;
        $violationsCount = 0;
        $averageDuration = 0;

        foreach ($stationnements as $stationnement) {
            if ($stationnement->getExitTime() === null) {
                $activeStationnements++;
            } else {
                $completedStationnements++;
                
                if ($stationnement->getPrice() !== null) {
                    $totalRevenue += $stationnement->getPrice()->getAmount();
                }
            }

            if ($stationnement->getHasPenalty() ?? false) {
                $violationsCount++;
            }
        }

        // Calculer la durée moyenne pour les stationnements terminés
        if ($completedStationnements > 0) {
            $totalDuration = 0;
            foreach ($stationnements as $stationnement) {
                if ($stationnement->getExitTime() !== null) {
                    $duration = $stationnement->getExitTime()->getTimestamp() - $stationnement->getEntryTime()->getTimestamp();
                    $totalDuration += $duration;
                }
            }
            $averageDuration = round($totalDuration / ($completedStationnements * 60), 2); // en minutes
        }

        return [
            'parking_id' => $parkingId,
            'parking_title' => $parking->getTitle(),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'active_only' => $activeOnly
            ],
            'statistics' => [
                'total_stationnements' => $totalStationnements,
                'active_stationnements' => $activeStationnements,
                'completed_stationnements' => $completedStationnements,
                'current_occupancy_rate' => $parking->getTotalSpots() > 0 ? 
                    round(($parking->getTotalSpots() - $parking->getAvailableSpots()) / $parking->getTotalSpots() * 100, 2) : 0,
                'total_revenue' => $totalRevenue,
                'average_duration_minutes' => $averageDuration,
                'violations_count' => $violationsCount
            ],
            'stationnements' => $stationnements
        ];
    }
}