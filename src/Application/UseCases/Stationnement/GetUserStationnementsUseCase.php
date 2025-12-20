<?php
declare(strict_types=1);

namespace App\Application\UseCases\Stationnement;

use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;

/**
 * Use Case pour récupérer les stationnements d'un utilisateur
 */
class GetUserStationnementsUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        StationnementRepositoryInterface $stationnementRepository,
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->stationnementRepository = $stationnementRepository;
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Récupère tous les stationnements d'un utilisateur avec les informations enrichies
     * 
     * @param string $userId ID de l'utilisateur
     * @return array Array d'objets avec les données enrichies
     */
    public function execute(string $userId): array
    {
        $stationnements = $this->stationnementRepository->findByUserId($userId);
        
        $result = [];
        foreach ($stationnements as $stationnement) {
            // Récupérer le parking
            $parking = $this->parkingRepository->findById($stationnement->getParkingId());
            $parkingName = $parking ? $parking->getTitle() : 'Parking inconnu';

            // Vérifier l'autorisation
            $isAuthorized = $this->checkAuthorization($stationnement);

            $result[] = [
                'stationnement' => $stationnement,
                'parkingName' => $parkingName,
                'isAuthorized' => $isAuthorized
            ];
        }

        return $result;
    }

    /**
     * Vérifie si le stationnement est autorisé
     */
    private function checkAuthorization($stationnement): bool
    {
        // Autorisé si abonnement
        if ($stationnement->hasAbonnement()) {
            return true;
        }

        // Autorisé si réservation et entrée dans le créneau
        if ($stationnement->hasReservation()) {
            $reservation = $this->reservationRepository->findById($stationnement->getReservationId());
            if ($reservation !== null) {
                $entryTime = $stationnement->getEntryTime();
                return $entryTime >= $reservation->getStartTime() 
                    && $entryTime <= $reservation->getEndTime();
            }
        }

        return false;
    }
}
