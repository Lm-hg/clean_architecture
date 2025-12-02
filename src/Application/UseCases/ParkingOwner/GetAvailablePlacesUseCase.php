<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\Exceptions\EntityNotFoundException;

class GetAvailablePlacesUseCase
{
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(ParkingRepositoryInterface $parkingRepository)
    {
        $this->parkingRepository = $parkingRepository;
    }

    public function execute(string $parkingId, string $ownerId): array
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

        // Obtenir le nombre de places disponibles en temps réel
        $availableSpots = $this->parkingRepository->countAvailableSpots($parkingId);

        return [
            'parking_id' => $parkingId,
            'total_spots' => $parking->getTotalSpots(),
            'available_spots' => $availableSpots,
            'occupied_spots' => $parking->getTotalSpots() - $availableSpots,
            'occupancy_rate' => round(($parking->getTotalSpots() - $availableSpots) / $parking->getTotalSpots() * 100, 2),
            'is_available' => $parking->isAvailable(),
            'last_updated' => new \DateTime()
        ];
    }
}