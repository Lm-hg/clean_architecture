<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Application\dtos\parking\CreateParkingDto;
use App\Application\dtos\parking\ParkingResponseDto;
use App\Domain\Entities\Parking;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ParkingOwnerRepositoryInterface;
use App\Domain\Exceptions\UnauthorizedAccessException;

class CreateParkingUseCase
{
    private ParkingRepositoryInterface $parkingRepository;
    private ParkingOwnerRepositoryInterface $parkingOwnerRepository;

    public function __construct(
        ParkingRepositoryInterface $parkingRepository,
        ParkingOwnerRepositoryInterface $parkingOwnerRepository
    ) {
        $this->parkingRepository = $parkingRepository;
        $this->parkingOwnerRepository = $parkingOwnerRepository;
    }

    public function execute(CreateParkingDto $createDto): ParkingResponseDto
    {
        // Vérifier que le propriétaire existe
        $owner = $this->parkingOwnerRepository->findById($createDto->ownerId);
        if ($owner === null) {
            throw new UnauthorizedAccessException('Propriétaire de parking non trouvé');
        }

        // Créer l'entité Parking
        $now = new \DateTime();
        $parking = new Parking(
            ownerId: $createDto->ownerId,
            title: $createDto->title,
            address: $createDto->address,
            coordinates: $createDto->coordinates,
            totalSpots: $createDto->totalSpots,
            tarifs: $createDto->tarifs,
            openingHours: $createDto->openingHours,
            createdAt: $now,
            updatedAt: $now,
            description: $createDto->description
        );

        // Sauvegarder en base de données
        $savedParking = $this->parkingRepository->save($parking);

        if ($savedParking === null) {
            throw new \RuntimeException('Erreur lors de la création du parking');
        }

        // Retourner la réponse
        return new ParkingResponseDto(
            id: $savedParking->getId(),
            ownerId: $savedParking->getOwnerId(),
            title: $savedParking->getTitle(),
            description: $savedParking->getDescription(),
            address: $savedParking->getAddress(),
            coordinates: $savedParking->getCoordinates(),
            totalSpots: $savedParking->getTotalSpots(),
            availableSpots: $savedParking->getAvailableSpots(),
            tarifs: $savedParking->getTarifs(),
            openingHours: $savedParking->getOpeningHours(),
            isAvailable: $savedParking->isAvailable(),
            createdAt: $savedParking->getCreatedAt(),
            updatedAt: $savedParking->getUpdatedAt()
        );
    }
}