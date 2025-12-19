<?php
declare(strict_types=1);

namespace App\Application\UseCases\Abonnement;

use App\Domain\Entities\Abonnement;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

/**
 * Use Case pour annuler un abonnement utilisateur
 */
class CancelUserSubscriptionUseCase
{
    private AbonnementRepositoryInterface $abonnementRepository;

    public function __construct(AbonnementRepositoryInterface $abonnementRepository)
    {
        $this->abonnementRepository = $abonnementRepository;
    }

    /**
     * Annule un abonnement
     * 
     * @param string $subscriptionId ID de l'abonnement
     * @param string $userId ID de l'utilisateur (pour vérification)
     * @return Abonnement L'abonnement annulé
     * @throws EntityNotFoundException Si l'abonnement n'existe pas
     * @throws \DomainException Si l'abonnement ne peut pas être annulé
     */
    public function execute(string $subscriptionId, string $userId): Abonnement
    {
        $abonnement = $this->abonnementRepository->findById($subscriptionId);
        
        if ($abonnement === null) {
            throw new EntityNotFoundException("Abonnement non trouvé");
        }

        // Vérifier que l'abonnement appartient bien à l'utilisateur
        if ($abonnement->getUserId() !== $userId) {
            throw new \DomainException("Vous n'êtes pas autorisé à annuler cet abonnement");
        }

        // Annuler l'abonnement (la méthode vérifie qu'il est actif)
        $abonnement->cancel();

        // Sauvegarder
        $saved = $this->abonnementRepository->save($abonnement);

        if ($saved === null) {
            throw new \RuntimeException("Échec de l'annulation de l'abonnement");
        }

        return $saved;
    }
}
