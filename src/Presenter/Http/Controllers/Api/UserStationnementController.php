<?php
declare(strict_types=1);

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Stationnement\GetUserStationnementsUseCase;

/**
 * Controller pour la gestion des stationnements utilisateurs
 * Respecte la Clean Architecture en appelant uniquement des Use Cases
 */
class UserStationnementController
{
    private GetUserStationnementsUseCase $getUserStationnementsUseCase;

    public function __construct(GetUserStationnementsUseCase $getUserStationnementsUseCase)
    {
        $this->getUserStationnementsUseCase = $getUserStationnementsUseCase;
    }

    /**
     * GET /api/user/stationnements
     * Récupère les stationnements de l'utilisateur connecté
     */
    public function index(string $userId): array
    {
        try {
            $results = $this->getUserStationnementsUseCase->execute($userId);

            $formatted = array_map(function($item) {
                $stationnement = $item['stationnement'];
                
                return [
                    'id' => $stationnement->getId(),
                    'userId' => $stationnement->getUserId(),
                    'parkingId' => $stationnement->getParkingId(),
                    'parkingName' => $item['parkingName'],
                    'vehiclePlate' => $stationnement->getVehiclePlate() ?? 'N/A',
                    'entryTime' => $stationnement->getEntryTime()->format(\DateTime::ATOM),
                    'exitTime' => $stationnement->getExitTime() 
                        ? $stationnement->getExitTime()->format(\DateTime::ATOM) 
                        : null,
                    'totalPrice' => $stationnement->getTotalAmount(),
                    'penalty' => $stationnement->getPenaltyAmount(),
                    'status' => $stationnement->getStatus(),
                    'reservationId' => $stationnement->getReservationId(),
                    'subscriptionId' => $stationnement->getAbonnementId(),
                    'isAuthorized' => $item['isAuthorized']
                ];
            }, $results);

            return [
                'status' => 'success',
                'data' => $formatted,
                'message' => 'Stationnements retrieved successfully'
            ];

        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Failed to retrieve stationnements: ' . $e->getMessage()
            ];
        }
    }
}
