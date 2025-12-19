<?php
declare(strict_types=1);

namespace App\Application\UseCases\Abonnement;

use App\Domain\Repositories\AbonnementRepositoryInterface;

/**
 * Use Case pour récupérer les abonnements d'un utilisateur
 */
class GetUserSubscriptionsUseCase
{
    private AbonnementRepositoryInterface $abonnementRepository;

    public function __construct(AbonnementRepositoryInterface $abonnementRepository)
    {
        $this->abonnementRepository = $abonnementRepository;
    }

    /**
     * Récupère tous les abonnements d'un utilisateur
     * 
     * @param string $userId ID de l'utilisateur
     * @return array Array of Abonnement entities
     */
    public function execute(string $userId): array
    {
        return $this->abonnementRepository->findByUserId($userId);
    }
}
