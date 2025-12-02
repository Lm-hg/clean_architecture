<?php
declare(strict_types=1);

namespace App\Application\UseCases\Stationnement;

use App\Domain\Entities\Stationnement;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

class EnterParkingUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private AbonnementRepositoryInterface $abonnementRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository,
        AbonnementRepositoryInterface $abonnementRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
        $this->abonnementRepository = $abonnementRepository;
    }

    /**
     * Enregistre l'entrée d'un utilisateur dans un parking
     * 
     * @param string $userId
     * @param string $parkingId
     * @param string|null $reservationId ID de réservation optionnelle
     * @param string|null $abonnementId ID d'abonnement optionnelle
     * @return Stationnement
     * @throws EntityNotFoundException Si le parking n'existe pas
     * @throws \DomainException Si le parking est plein ou fermé
     */
    public function execute(
        string $userId,
        string $parkingId,
        ?string $reservationId = null,
        ?string $abonnementId = null
    ): Stationnement {
        $now = new \DateTime();

        // 1. Vérifier que le parking existe
        $parking = $this->parkingRepository->findById($parkingId);
        if ($parking === null) {
            throw new EntityNotFoundException("Parking non trouvé");
        }

        // 2. Vérifier que le parking est ouvert
        if (!$parking->isOpenAt($now)) {
            throw new \DomainException("Le parking est fermé à cet instant");
        }

        // 3. Vérifier qu'il y a des places disponibles
        if (!$parking->hasAvailableSpots()) {
            throw new \DomainException("Le parking est complet");
        }

        // 4. Si une réservation est fournie, vérifier qu'elle existe et est valide
        if ($reservationId !== null) {
            $reservation = $this->reservationRepository->findById($reservationId);
            if ($reservation === null) {
                throw new EntityNotFoundException("Réservation non trouvée");
            }
            if (!$reservation->belongsToUser($userId)) {
                throw new \DomainException("Cette réservation n'appartient pas à cet utilisateur");
            }
            if (!$reservation->isActiveAt($now)) {
                throw new \DomainException("La réservation n'est pas active à cet instant");
            }
        }

        // 5. Si un abonnement est fourni, vérifier qu'il existe et est valide
        if ($abonnementId !== null) {
            $abonnement = $this->abonnementRepository->findById($abonnementId);
            if ($abonnement === null) {
                throw new EntityNotFoundException("Abonnement non trouvé");
            }
            if (!$abonnement->belongsToUser($userId)) {
                throw new \DomainException("Cet abonnement n'appartient pas à cet utilisateur");
            }
            if (!$abonnement->isValidAt($now)) {
                throw new \DomainException("L'abonnement n'est pas valide à cet instant");
            }
        }

        // 6. Vérifier qu'il n'y a pas déjà un stationnement actif pour cet utilisateur dans ce parking
        $activeStationnements = $this->stationnementRepository->findActiveByUserId($userId);
        foreach ($activeStationnements as $active) {
            if ($active->isForParking($parkingId)) {
                throw new \DomainException("Vous avez déjà un stationnement actif dans ce parking");
            }
        }

        // 7. Créer le stationnement
        $stationnement = new Stationnement(
            $userId,
            $parkingId,
            $now,
            $now,
            $now,
            $reservationId,
            $abonnementId
        );

        // 8. Décrémenter le nombre de places disponibles
        $parking->decrementAvailableSpots();
        $this->parkingRepository->save($parking);

        // 9. Sauvegarder le stationnement
        return $this->stationnementRepository->save($stationnement);
    }
}

