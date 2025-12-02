<?php
declare(strict_types=1);

namespace App\Application\UseCases\Stationnement;

use App\Domain\Entities\Stationnement;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Services\PricingService;
use App\Domain\Services\PenaltyCalculator;

class ExitParkingUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private AbonnementRepositoryInterface $abonnementRepository;
    private PricingService $pricingService;
    private PenaltyCalculator $penaltyCalculator;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository,
        AbonnementRepositoryInterface $abonnementRepository,
        PricingService $pricingService,
        PenaltyCalculator $penaltyCalculator
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
        $this->abonnementRepository = $abonnementRepository;
        $this->pricingService = $pricingService;
        $this->penaltyCalculator = $penaltyCalculator;
    }

    /**
     * Enregistre la sortie d'un utilisateur d'un parking et calcule le prix
     * 
     * @param string $stationnementId
     * @param string $userId
     * @return Stationnement
     * @throws EntityNotFoundException Si le stationnement n'existe pas
     * @throws \DomainException Si le stationnement n'appartient pas à l'utilisateur ou est déjà terminé
     */
    public function execute(string $stationnementId, string $userId): Stationnement
    {
        $exitTime = new \DateTime();

        // 1. Récupérer le stationnement
        $stationnement = $this->stationnementRepository->findById($stationnementId);
        if ($stationnement === null) {
            throw new EntityNotFoundException("Stationnement non trouvé");
        }

        // 2. Vérifier que le stationnement appartient à l'utilisateur
        if (!$stationnement->belongsToUser($userId)) {
            throw new \DomainException("Ce stationnement n'appartient pas à cet utilisateur");
        }

        // 3. Vérifier que le stationnement est actif
        if (!$stationnement->isActive()) {
            throw new \DomainException("Ce stationnement est déjà terminé");
        }

        // 4. Récupérer le parking pour le calcul du prix
        $parking = $this->parkingRepository->findById($stationnement->getParkingId());
        if ($parking === null) {
            throw new EntityNotFoundException("Parking non trouvé");
        }

        // 5. Calculer le prix avec le PricingService
        $price = $this->pricingService->calculateParkingPrice($stationnement, $parking, $exitTime);

        // 6. Vérifier et calculer les pénalités si nécessaire
        $reservation = null;
        $abonnement = null;
        
        if ($stationnement->hasReservation()) {
            $reservation = $this->reservationRepository->findById($stationnement->getReservationId());
        }
        
        if ($stationnement->hasAbonnement()) {
            $abonnement = $this->abonnementRepository->findById($stationnement->getAbonnementId());
        }

        // Calculer la pénalité avec le PenaltyCalculator
        // Note: Pour l'abonnement, on devrait calculer le slotEndTime, mais pour simplifier on utilise la fin de l'abonnement
        $abonnementSlotEndTime = $abonnement !== null ? $abonnement->getEndDate() : null;
        $penaltyAmount = $this->penaltyCalculator->calculatePenalty(
            $stationnement,
            $reservation,
            $abonnement,
            $abonnementSlotEndTime
        );

        if ($penaltyAmount > 0) {
            $stationnement->applyPenalty($penaltyAmount);
        }

        // 7. Enregistrer la sortie
        $stationnement->exit($exitTime);
        $stationnement->setPrice($price);

        // 8. Incrémenter le nombre de places disponibles
        // Vérifier que le parking n'a pas déjà toutes ses places disponibles
        if ($parking->getAvailableSpots() < $parking->getTotalSpots()) {
            $parking->incrementAvailableSpots();
            $this->parkingRepository->save($parking);
        }

        // 9. Sauvegarder le stationnement
        return $this->stationnementRepository->save($stationnement);
    }
}

