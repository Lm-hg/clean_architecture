<?php
declare(strict_types=1);

namespace App\Application\UseCases\ParkingOwner;

use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedAccessException;

class UpdateParkingHoursUseCase
{
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(ParkingRepositoryInterface $parkingRepository)
    {
        $this->parkingRepository = $parkingRepository;
    }

    /**
     * Met à jour les horaires d'ouverture d'un parking
     * 
     * @param string $parkingId
     * @param string $ownerId
     * @param OpeningHours $openingHours
     * @return void
     * @throws EntityNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function execute(string $parkingId, string $ownerId, OpeningHours $openingHours): void
    {
        // Récupérer le parking
        $parking = $this->parkingRepository->findById($parkingId);
        
        if ($parking === null) {
            throw new EntityNotFoundException('Parking non trouvé');
        }

        // Vérifier que le propriétaire est bien le propriétaire du parking
        if (!$parking->belongsToOwner($ownerId)) {
            throw new UnauthorizedAccessException('Vous n\'avez pas accès à ce parking');
        }

        // Mettre à jour les horaires
        $parking->updateOpeningHours($openingHours);

        // Sauvegarder
        $this->parkingRepository->save($parking);
    }
}

