<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Application\dtos\parking\UpdateParkingDto;
use App\Application\dtos\parking\ParkingResponseDto;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\Exceptions\EntityNotFoundException;

class UpdateParkingTarifsUseCase
{
    private ParkingRepositoryInterface $parkingRepository;

    public function __construct(ParkingRepositoryInterface $parkingRepository)
    {
        $this->parkingRepository = $parkingRepository;
    }

    public function execute(string $parkingId, string $ownerId, UpdateParkingDto $updateDto): ParkingResponseDto
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

        // Mettre à jour uniquement les tarifs si fournis
        if ($updateDto->tarifs !== null) {
            $parking->updateTarifs($updateDto->tarifs);
        }

        // Mettre à jour la date de modification
        $parking->setUpdatedAt(new \DateTime());

        // Sauvegarder
        $updatedParking = $this->parkingRepository->update($parking);

        if ($updatedParking === null) {
            throw new \RuntimeException('Erreur lors de la mise à jour des tarifs');
        }

        // Retourner la réponse
        return new ParkingResponseDto(
            id: $updatedParking->getId(),
            ownerId: $updatedParking->getOwnerId(),
            title: $updatedParking->getTitle(),
            description: $updatedParking->getDescription(),
            address: $updatedParking->getAddress(),
            coordinates: $updatedParking->getCoordinates(),
            totalSpots: $updatedParking->getTotalSpots(),
            availableSpots: $updatedParking->getAvailableSpots(),
            tarifs: $updatedParking->getTarifs(),
            openingHours: $updatedParking->getOpeningHours(),
            isAvailable: $updatedParking->isAvailable(),
            createdAt: $updatedParking->getCreatedAt(),
            updatedAt: $updatedParking->getUpdatedAt()
        );
    }
}