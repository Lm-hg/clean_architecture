<?php

namespace App\Application\UseCases\User;

use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

class ListUserStatonnementsUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->userRepository = $userRepository;
    }

    public function execute(string $userId, ?\DateTime $startDate = null, ?\DateTime $endDate = null, ?bool $activeOnly = false): array
    {
        // Vérifier que l'utilisateur existe
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('Utilisateur non trouvé');
        }

        // Récupérer tous les stationnements de l'utilisateur
        $stationnements = $this->stationnementRepository->findByUserId($userId);

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

        // Trier par date d'entrée décroissante (plus récent en premier)
        usort($stationnements, function ($a, $b) {
            return $b->getEntryTime() <=> $a->getEntryTime();
        });

        // Calculer les statistiques
        $totalStationnements = count($stationnements);
        $activeStationnements = 0;
        $completedStationnements = 0;
        $totalDuration = 0;
        $totalAmount = 0;
        $violationsCount = 0;

        foreach ($stationnements as $stationnement) {
            if ($stationnement->getExitTime() === null) {
                $activeStationnements++;
            } else {
                $completedStationnements++;
                $duration = $stationnement->getExitTime()->getTimestamp() - $stationnement->getEntryTime()->getTimestamp();
                $totalDuration += $duration;
                
                if ($stationnement->getPrice() !== null) {
                    $totalAmount += $stationnement->getPrice()->getAmount();
                }
            }

            if ($stationnement->getHasPenalty() ?? false) {
                $violationsCount++;
            }
        }

        return [
            'user_id' => $userId,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'active_only' => $activeOnly
            ],
            'statistics' => [
                'total_stationnements' => $totalStationnements,
                'active_stationnements' => $activeStationnements,
                'completed_stationnements' => $completedStationnements,
                'total_duration_seconds' => $totalDuration,
                'average_duration_minutes' => $completedStationnements > 0 ? round($totalDuration / ($completedStationnements * 60), 2) : 0,
                'total_amount_paid' => $totalAmount,
                'violations_count' => $violationsCount
            ],
            'stationnements' => $stationnements
        ];
    }
}