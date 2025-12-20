<?php
declare(strict_types=1);

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Reservation\CancelReservationUseCase;
use App\Application\UseCases\Reservation\CreateReservationUseCase;
use App\Application\UseCases\Reservation\GetReservationUseCase;
use App\Application\UseCases\Reservation\ListReservationsForUserUseCase;

class ReservationController
{
    private CreateReservationUseCase $createUseCase;
    private GetReservationUseCase $getUseCase;
    private ListReservationsForUserUseCase $listUseCase;
    private CancelReservationUseCase $cancelUseCase;

    public function __construct(
        CreateReservationUseCase $createUseCase,
        GetReservationUseCase $getUseCase,
        ListReservationsForUserUseCase $listUseCase,
        CancelReservationUseCase $cancelUseCase
    ) {
        $this->createUseCase = $createUseCase;
        $this->getUseCase = $getUseCase;
        $this->listUseCase = $listUseCase;
        $this->cancelUseCase = $cancelUseCase;
    }

    public function create(array $data): array
    {
        try {
            // Débogage : Enregistrer les données reçues dans le contrôleur
            error_log("ReservationController received: " . json_encode($data));
            
            // Valider les champs requis
            if (!isset($data['user_id'], $data['parking_id'], $data['start_time'], $data['end_time'])) {
                $missing = [];
                if (!isset($data['user_id'])) $missing[] = 'user_id';
                if (!isset($data['parking_id'])) $missing[] = 'parking_id';
                if (!isset($data['start_time'])) $missing[] = 'start_time';
                if (!isset($data['end_time'])) $missing[] = 'end_time';
                
                throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', $missing));
            }

            // Parser les dates depuis UTC et convertir vers le fuseau horaire Europe/Paris
            $start = new \DateTime($data['start_time'], new \DateTimeZone('UTC'));
            $start->setTimezone(new \DateTimeZone('Europe/Paris'));
            
            $end = new \DateTime($data['end_time'], new \DateTimeZone('UTC'));
            $end->setTimezone(new \DateTimeZone('Europe/Paris'));

            // Valider une durée minimale de 15 minutes
            $durationInMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
            error_log("Reservation duration: $durationInMinutes minutes (start: " . $start->format('Y-m-d H:i:s') . ", end: " . $end->format('Y-m-d H:i:s') . ")");
            
            if ($durationInMinutes < 15) {
                throw new \InvalidArgumentException('La durée de réservation doit être d\'au moins 15 minutes.');
            }

            $reservation = $this->createUseCase->execute(
                $data['user_id'],
                $data['parking_id'],
                $start,
                $end
            );

            return [
                'status' => 'success',
                'data' => [
                    'id' => $reservation->getId(),
                    'userId' => $reservation->getUserId(),
                    'parkingId' => $reservation->getParkingId(),
                    'status' => $reservation->getStatus(),
                    'startTime' => $reservation->getStartTime()->format(\DateTime::ATOM),
                    'endTime' => $reservation->getEndTime()->format(\DateTime::ATOM),
                    'totalPrice' => $reservation->getPrice() ? $reservation->getPrice()->getAmount() : 0,
                    'createdAt' => $reservation->getCreatedAt()->format(\DateTime::ATOM),
                    'updatedAt' => $reservation->getUpdatedAt()->format(\DateTime::ATOM)
                ],
                'message' => 'Reservation created successfully'
            ];
        } catch (\InvalidArgumentException | \DomainException $e) {
            http_response_code(400);
            error_log("Reservation creation failed (400): " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'receivedData' => array_keys($data) // Pour débogage
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Reservation creation failed (500): " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
        }
    }

    public function show(string $id): array
    {
        $reservation = $this->getUseCase->execute($id);

        if (!$reservation) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => 'Reservation not found'
            ];
        }

        // Récupérer la pénalité et le dépassement depuis le stationnement s'il existe
        $penalty = null;
        $overstayDuration = null;
        
        // Nous devons vérifier la base de données pour le stationnement lié à cette réservation
        // Ceci est une solution rapide - idéalement devrait être dans un use case
        try {
            $pdo = require BASE_PATH . '/config/database.php';
            $stmt = $pdo->prepare("SELECT penalties, entry_time, exit_time FROM stationnements WHERE reservation_id = :reservation_id ORDER BY entry_time DESC LIMIT 1");
            $stmt->execute([':reservation_id' => $id]);
            $stationnement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($stationnement) {
                if ($stationnement['penalties'] > 0) {
                    $penalty = (float)$stationnement['penalties'];
                }
                if ($stationnement['exit_time'] && $stationnement['entry_time']) {
                    $actualEntry = new \DateTime($stationnement['entry_time']);
                    $actualExit = new \DateTime($stationnement['exit_time']);
                    
                    // Calculer l'heure de sortie autorisée basée sur la durée réservée depuis l'entrée réelle
                    $reservedDuration = $reservation->getEndTime()->getTimestamp() - $reservation->getStartTime()->getTimestamp();
                    $allowedExitTime = clone $actualEntry;
                    $allowedExitTime->modify('+' . $reservedDuration . ' seconds');
                    
                    if ($actualExit > $allowedExitTime) {
                        $overstayDuration = ($actualExit->getTimestamp() - $allowedExitTime->getTimestamp()) / 60;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }

        return [
            'status' => 'success',
            'data' => [
                'id' => $reservation->getId(),
                'userId' => $reservation->getUserId(),
                'parkingId' => $reservation->getParkingId(),
                'status' => $reservation->getStatus(),
                'startTime' => $reservation->getStartTime()->format(\DateTime::ATOM),
                'endTime' => $reservation->getEndTime()->format(\DateTime::ATOM),
                'totalPrice' => $reservation->getTotalPrice(),
                'penalty' => $penalty,
                'overstayDuration' => $overstayDuration,
                'paymentStatus' => $reservation->getIsPaid() ? 'paid' : 'unpaid',
                'createdAt' => $reservation->getCreatedAt()->format(\DateTime::ATOM),
                'updatedAt' => $reservation->getUpdatedAt()->format(\DateTime::ATOM)
            ]
        ];
    }

    public function index(string $userId): array
    {
        $reservations = $this->listUseCase->execute($userId);

        $data = array_map(function ($reservation) {
            // Récupérer le nom du parking
            $parkingName = 'Parking';
            $stationnementId = null;
            $entryTime = null;
            $exitTime = null;
            
            try {
                $pdo = require BASE_PATH . '/config/database.php';
                $stmt = $pdo->prepare("SELECT title FROM parkings WHERE id = :id");
                $stmt->execute([':id' => $reservation->getParkingId()]);
                $parking = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($parking) {
                    $parkingName = $parking['title'];
                }
                
                // Récupérer le stationnement lié à cette réservation
                $stmt = $pdo->prepare("SELECT id, entry_time, exit_time FROM stationnements WHERE reservation_id = :reservation_id ORDER BY entry_time DESC LIMIT 1");
                $stmt->execute([':reservation_id' => $reservation->getId()]);
                $stationnement = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($stationnement) {
                    $stationnementId = $stationnement['id'];
                    // Convertir les dates SQL au format ISO
                    if ($stationnement['entry_time']) {
                        $entryTime = (new \DateTime($stationnement['entry_time']))->format(\DateTime::ATOM);
                    }
                    if ($stationnement['exit_time']) {
                        $exitTime = (new \DateTime($stationnement['exit_time']))->format(\DateTime::ATOM);
                    }
                }
            } catch (\Exception $e) {
                // Ignorer l'erreur et utiliser la valeur par défaut
            }
            
            return [
                'id' => $reservation->getId(),
                'parkingId' => $reservation->getParkingId(),
                'parkingName' => $parkingName,
                'userId' => $reservation->getUserId(),
                'status' => $reservation->getStatus(),
                'startTime' => $reservation->getStartTime()->format(\DateTime::ATOM),
                'endTime' => $reservation->getEndTime()->format(\DateTime::ATOM),
                'totalPrice' => $reservation->getTotalPrice(),
                'stationnementId' => $stationnementId,
                'entryTime' => $entryTime,
                'exitTime' => $exitTime,
                'createdAt' => $reservation->getCreatedAt()->format(\DateTime::ATOM),
                'updatedAt' => $reservation->getUpdatedAt()->format(\DateTime::ATOM)
            ];
        }, $reservations);

        return [
            'status' => 'success',
            'data' => $data
        ];
    }

    public function cancel(string $id): array
    {
        try {
            $this->cancelUseCase->execute($id);

            return [
                'status' => 'success',
                'message' => 'Reservation cancelled successfully'
            ];
        } catch (\InvalidArgumentException | \DomainException $e) {
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Internal server error'
            ];
        }
    }
}

