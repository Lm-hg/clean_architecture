<?php
declare(strict_types=1);

namespace App\Application\UseCases\Abonnement;

use App\Domain\Repositories\SubscriptionTypeRepositoryInterface;

/**
 * Use Case pour récupérer les types d'abonnements disponibles pour un parking
 */
class GetSubscriptionTypesUseCase
{
    private SubscriptionTypeRepositoryInterface $subscriptionTypeRepository;

    public function __construct(SubscriptionTypeRepositoryInterface $subscriptionTypeRepository)
    {
        $this->subscriptionTypeRepository = $subscriptionTypeRepository;
    }

    /**
     * Récupère tous les types d'abonnements actifs pour un parking
     * 
     * @param string $parkingId
     * @return array Array of SubscriptionType entities
     */
    public function execute(string $parkingId): array
    {
        return $this->subscriptionTypeRepository->findActiveByParkingId($parkingId);
    }
}
