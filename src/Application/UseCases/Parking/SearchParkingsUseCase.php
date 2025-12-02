<?php
declare(strict_types=1);

namespace App\Application\UseCases\Parking;

use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Application\dtos\parking\ParkingResponseDto;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;

class SearchParkingsUseCase
{
    private ParkingRepositoryInterface $parkingRepository;
    private ReservationRepositoryInterface $reservationRepository;

    public function __construct(
        ParkingRepositoryInterface $parkingRepository,
        ReservationRepositoryInterface $reservationRepository
    ) {
        $this->parkingRepository = $parkingRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Recherche des parkings avec places disponibles autour d'une coordonnée GPS
     * 
     * @param float $latitude
     * @param float $longitude
     * @param float $radius Radius in kilometers (default: 5km)
     * @param \DateTime|null $startTime Optional: check availability for a specific time range
     * @param \DateTime|null $endTime Optional: check availability for a specific time range
     * @return ParkingResponseDto[]
     */
    public function execute(
        float $latitude,
        float $longitude,
        float $radius = 5.0,
        ?\DateTime $startTime = null,
        ?\DateTime $endTime = null
    ): array {
        // Validation des coordonnées
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180');
        }
        if ($radius <= 0 || $radius > 100) {
            throw new \InvalidArgumentException('Radius must be between 0 and 100 km');
        }

        // Recherche des parkings dans le rayon
        $parkings = $this->parkingRepository->findByLocation($latitude, $longitude, $radius);

        // Filtrer uniquement les parkings disponibles
        $availableParkings = array_filter($parkings, function ($parking) {
            return $parking->getIsAvailable() && $parking->hasAvailableSpots();
        });

        // Si des dates sont fournies, vérifier la disponibilité sur le créneau
        if ($startTime !== null && $endTime !== null) {
            $availableParkings = array_filter($availableParkings, function ($parking) use ($startTime, $endTime) {
                // Vérifier que le parking est ouvert pendant le créneau
                if (!$parking->isOpenDuring($startTime, $endTime)) {
                    return false;
                }

                // Vérifier qu'il n'y a pas de réservations qui chevauchent
                $overlappingReservations = $this->reservationRepository->findReservationsInInterval(
                    $parking->getId(),
                    $startTime,
                    $endTime
                );

                // Calculer le nombre de places occupées par les réservations
                $occupiedSpots = count($overlappingReservations);
                
                // Vérifier qu'il reste assez de places
                return ($parking->getAvailableSpots() - $occupiedSpots) > 0;
            });
        }

        // Convertir en DTOs
        return array_map(function ($parking) {
            return $this->toDto($parking);
        }, $availableParkings);
    }

    private function toDto(\App\Domain\Entities\Parking $parking): ParkingResponseDto
    {
        return new ParkingResponseDto(
            $parking->getId(),
            $parking->getOwnerId(),
            $parking->getTitle(),
            $parking->getDescription(),
            $parking->getAddress(),
            $parking->getCoordinates(),
            $parking->getTotalSpots(),
            $parking->getAvailableSpots(),
            $parking->getTarifs(),
            $parking->getOpeningHours(),
            $parking->getIsAvailable(),
            $parking->getCreatedAt(),
            $parking->getUpdatedAt()
        );
    }
}

