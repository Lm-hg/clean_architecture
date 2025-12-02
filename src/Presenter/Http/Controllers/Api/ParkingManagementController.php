<?php

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\ParkingOwner\CreateParkingUseCase;
use App\Application\UseCases\ParkingOwner\UpdateParkingTarifsUseCase;
use App\Application\UseCases\ParkingOwner\GetAvailablePlacesUseCase;
use App\Application\UseCases\ParkingOwner\ListParkingReservationsUseCase;
use App\Application\UseCases\ParkingOwner\CalculateMonthlyRevenueUseCase;
use App\Application\dtos\parking\CreateParkingDto;
use App\Application\dtos\parking\UpdateParkingDto;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;
use App\Domain\Exceptions\UnauthorizedAccessException;
use App\Domain\Exceptions\EntityNotFoundException;

class ParkingManagementController
{
    private CreateParkingUseCase $createParkingUseCase;
    private UpdateParkingTarifsUseCase $updateTarifsUseCase;
    private GetAvailablePlacesUseCase $getAvailablePlacesUseCase;
    private ListParkingReservationsUseCase $listReservationsUseCase;
    private CalculateMonthlyRevenueUseCase $calculateRevenueUseCase;

    public function __construct(
        CreateParkingUseCase $createParkingUseCase,
        UpdateParkingTarifsUseCase $updateTarifsUseCase,
        GetAvailablePlacesUseCase $getAvailablePlacesUseCase,
        ListParkingReservationsUseCase $listReservationsUseCase,
        CalculateMonthlyRevenueUseCase $calculateRevenueUseCase
    ) {
        $this->createParkingUseCase = $createParkingUseCase;
        $this->updateTarifsUseCase = $updateTarifsUseCase;
        $this->getAvailablePlacesUseCase = $getAvailablePlacesUseCase;
        $this->listReservationsUseCase = $listReservationsUseCase;
        $this->calculateRevenueUseCase = $calculateRevenueUseCase;
    }

    /**
     * POST /api/parking-owners/{ownerId}/parkings
     * Créer un nouveau parking
     */
    
    public function createParking(string $ownerId, array $requestData): array
    {
        try {
            // Validation des données requises
            $requiredFields = ['title', 'address', 'coordinates', 'totalSpots', 'tarifs', 'openingHours'];
            foreach ($requiredFields as $field) {
                if (!isset($requestData[$field])) {
                    return [
                        'success' => false,
                        'error' => "Champ requis manquant: {$field}",
                        'status' => 400
                    ];
                }
            }

            // Validation des coordonnées GPS
            if (!isset($requestData['coordinates']['latitude']) || !isset($requestData['coordinates']['longitude'])) {
                return [
                    'success' => false,
                    'error' => 'Coordonnées GPS invalides (latitude et longitude requises)',
                    'status' => 400
                ];
            }

            // Créer les ValueObjects (simplifié pour l'exemple)
            $address = new Address(
                $requestData['address']['street'] ?? '',
                $requestData['address']['city'] ?? '',
                $requestData['address']['zipCode'] ?? '',
                $requestData['address']['country'] ?? 'France'
            );

            $coordinates = new GPSCoordinates(
                (float)$requestData['coordinates']['latitude'],
                (float)$requestData['coordinates']['longitude']
            );

            // Pour l'exemple, créer des objets simplifiés
            // En réalité, ces ValueObjects auraient une construction plus complexe
            $tarifs = new TarifCollection(); // À implémenter selon les besoins
            $openingHours = new OpeningHours(); // À implémenter selon les besoins

            // Créer le DTO
            $createDto = new CreateParkingDto(
                ownerId: $ownerId,
                title: trim($requestData['title']),
                address: $address,
                coordinates: $coordinates,
                totalSpots: (int)$requestData['totalSpots'],
                tarifs: $tarifs,
                openingHours: $openingHours,
                description: $requestData['description'] ?? null
            );

            // Exécuter le use case
            $response = $this->createParkingUseCase->execute($createDto);

            return [
                'success' => true,
                'data' => [
                    'id' => $response->id,
                    'title' => $response->title,
                    'description' => $response->description,
                    'totalSpots' => $response->totalSpots,
                    'availableSpots' => $response->availableSpots,
                    'isAvailable' => $response->isAvailable,
                    'createdAt' => $response->createdAt->format('Y-m-d H:i:s')
                ],
                'message' => 'Parking créé avec succès',
                'status' => 201
            ];

        } catch (UnauthorizedAccessException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 403
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la création du parking: ' . $e->getMessage(),
                'status' => 500
            ];
        }
    }

    /**
     * PUT /api/parking-owners/{ownerId}/parkings/{parkingId}/tarifs
     * Mettre à jour les tarifs d'un parking
     */
    public function updateTarifs(string $ownerId, string $parkingId, array $requestData): array
    {
        try {
            // Validation basique
            if (!isset($requestData['tarifs'])) {
                return [
                    'success' => false,
                    'error' => 'Données tarifaires manquantes',
                    'status' => 400
                ];
            }

            // Créer le DTO (simplifié)
            $tarifs = new TarifCollection(); // À implémenter selon les besoins
            $updateDto = new UpdateParkingDto(
                tarifs: $tarifs
            );

            // Exécuter le use case
            $response = $this->updateTarifsUseCase->execute($parkingId, $ownerId, $updateDto);

            return [
                'success' => true,
                'data' => [
                    'id' => $response->id,
                    'title' => $response->title,
                    'updatedAt' => $response->updatedAt->format('Y-m-d H:i:s')
                ],
                'message' => 'Tarifs mis à jour avec succès',
                'status' => 200
            ];

        } catch (UnauthorizedAccessException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 403
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la mise à jour des tarifs',
                'status' => 500
            ];
        }
    }

    /**
     * GET /api/parking-owners/{ownerId}/parkings/{parkingId}/availability
     * Obtenir les places disponibles en temps réel
     */
    public function getAvailability(string $ownerId, string $parkingId): array
    {
        try {
            $availability = $this->getAvailablePlacesUseCase->execute($parkingId, $ownerId);

            return [
                'success' => true,
                'data' => $availability,
                'status' => 200
            ];

        } catch (UnauthorizedAccessException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 403
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la récupération des disponibilités',
                'status' => 500
            ];
        }
    }

    /**
     * GET /api/parking-owners/{ownerId}/parkings/{parkingId}/reservations
     * Lister les réservations d'un parking
     */
    public function getReservations(string $ownerId, string $parkingId, array $queryParams = []): array
    {
        try {
            $startDate = isset($queryParams['start_date']) ? 
                new \DateTime($queryParams['start_date']) : null;
            $endDate = isset($queryParams['end_date']) ? 
                new \DateTime($queryParams['end_date']) : null;

            $result = $this->listReservationsUseCase->execute($parkingId, $ownerId, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $result,
                'status' => 200
            ];

        } catch (UnauthorizedAccessException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 403
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la récupération des réservations',
                'status' => 500
            ];
        }
    }

    /**
     * GET /api/parking-owners/{ownerId}/parkings/{parkingId}/revenue
     * Calculer les revenus mensuels
     */
    public function getMonthlyRevenue(string $ownerId, string $parkingId, array $queryParams = []): array
    {
        try {
            $month = isset($queryParams['month']) ? (int)$queryParams['month'] : (int)date('m');
            $year = isset($queryParams['year']) ? (int)$queryParams['year'] : (int)date('Y');

            $result = $this->calculateRevenueUseCase->execute($parkingId, $ownerId, $month, $year);

            return [
                'success' => true,
                'data' => $result,
                'status' => 200
            ];

        } catch (UnauthorizedAccessException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 403
            ];
        } catch (EntityNotFoundException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 404
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors du calcul des revenus',
                'status' => 500
            ];
        }
    }
}