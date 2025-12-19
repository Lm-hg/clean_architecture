<?php
declare(strict_types=1);

namespace App\Application\UseCases\Abonnement;

use App\Domain\Entities\Abonnement;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Repositories\SubscriptionTypeRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

/**
 * Use Case pour créer un abonnement pour un utilisateur
 */
class CreateUserSubscriptionUseCase
{
    private AbonnementRepositoryInterface $abonnementRepository;
    private SubscriptionTypeRepositoryInterface $subscriptionTypeRepository;

    public function __construct(
        AbonnementRepositoryInterface $abonnementRepository,
        SubscriptionTypeRepositoryInterface $subscriptionTypeRepository
    ) {
        $this->abonnementRepository = $abonnementRepository;
        $this->subscriptionTypeRepository = $subscriptionTypeRepository;
    }

    /**
     * Crée un nouvel abonnement pour un utilisateur
     * 
     * @param string $userId ID de l'utilisateur
     * @param string $subscriptionTypeId ID du type d'abonnement
     * @param \DateTime|null $startDate Date de début (null = maintenant)
     * @return Abonnement L'abonnement créé
     * @throws EntityNotFoundException Si le type d'abonnement n'existe pas
     * @throws \DomainException Si le type d'abonnement est inactif
     */
    public function execute(
        string $userId,
        string $subscriptionTypeId,
        ?\DateTime $startDate = null
    ): Abonnement {
        // Récupérer le type d'abonnement
        $subscriptionType = $this->subscriptionTypeRepository->findById($subscriptionTypeId);
        
        if ($subscriptionType === null) {
            throw new EntityNotFoundException("Type d'abonnement non trouvé");
        }

        if (!$subscriptionType->isActive()) {
            throw new \DomainException("Ce type d'abonnement n'est plus actif");
        }

        // Calculer les dates
        $startDate = $startDate ?? new \DateTime();
        $endDate = (clone $startDate)->modify('+' . $subscriptionType->getDurationDays() . ' days');

        $now = new \DateTime();

        // Créer l'abonnement avec les arguments dans le bon ordre
        // Constructeur: userId, parkingId, type, timeSlots, startDate, endDate, monthlyPrice, createdAt, updatedAt, id = null
        $abonnement = new Abonnement(
            $userId,
            $subscriptionType->getParkingId(),
            $subscriptionType->getName(), // type
            $subscriptionType->getTimeSlots(),
            $startDate,
            $endDate,
            $subscriptionType->getPrice(),
            $now, // createdAt
            $now  // updatedAt
            // id est optionnel et sera null par défaut
        );

        // L'abonnement est déjà actif par défaut (STATUS_ACTIVE dans le constructeur)
        // Marquer comme payé pour simplifier (normalement géré par un autre Use Case)
        $abonnement->markAsPaid();

        // Sauvegarder
        $saved = $this->abonnementRepository->save($abonnement);

        if ($saved === null) {
            throw new \RuntimeException("Échec de la création de l'abonnement");
        }

        return $saved;
    }
}
