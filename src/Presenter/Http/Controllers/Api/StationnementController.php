<?php
declare(strict_types=1);

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Stationnement\EnterParkingUseCase;
use App\Application\UseCases\Stationnement\ExitParkingUseCase;
use App\Application\UseCases\Stationnement\GetStationnementUseCase;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

class StationnementController
{
    private EnterParkingUseCase $enterUseCase;
    private ExitParkingUseCase $exitUseCase;
    private GetStationnementUseCase $getUseCase;
    private StationnementRepositoryInterface $stationnementRepository;

    public function __construct(
        EnterParkingUseCase $enterUseCase,
        ExitParkingUseCase $exitUseCase,
        GetStationnementUseCase $getUseCase,
        StationnementRepositoryInterface $stationnementRepository
    ) {
        $this->enterUseCase = $enterUseCase;
        $this->exitUseCase = $exitUseCase;
        $this->getUseCase = $getUseCase;
        $this->stationnementRepository = $stationnementRepository;
    }

    /**
     * POST /api/stationnements/enter
     * Enregistre l'entrée d'un utilisateur dans un parking
     */
    public function enter(array $data): array
    {
        try {
            // Valider les champs requis
            if (!isset($data['user_id'], $data['parking_id'])) {
                throw new \InvalidArgumentException('Missing required fields: user_id and parking_id are required');
            }

            $reservationId = $data['reservation_id'] ?? null;
            $abonnementId = $data['abonnement_id'] ?? null;

            $stationnement = $this->enterUseCase->execute(
                $data['user_id'],
                $data['parking_id'],
                $reservationId,
                $abonnementId
            );

            return [
                'status' => 'success',
                'data' => [
                    'id' => $stationnement->getId(),
                    'userId' => $stationnement->getUserId(),
                    'parkingId' => $stationnement->getParkingId(),
                    'entryTime' => $stationnement->getEntryTime()->format(\DateTime::ATOM),
                    'status' => $stationnement->getStatus(),
                    'reservationId' => $stationnement->getReservationId(),
                    'abonnementId' => $stationnement->getAbonnementId(),
                    'createdAt' => $stationnement->getCreatedAt()->format(\DateTime::ATOM)
                ]
            ];
        } catch (EntityNotFoundException $e) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
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
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/stationnements/{id}/exit
     * Enregistre la sortie d'un utilisateur d'un parking
     */
    public function exit(string $id, array $data): array
    {
        try {
            // Valider les champs requis
            if (!isset($data['user_id'])) {
                throw new \InvalidArgumentException('Missing required field: user_id');
            }

            $stationnement = $this->exitUseCase->execute($id, $data['user_id']);

            return [
                'status' => 'success',
                'data' => [
                    'id' => $stationnement->getId(),
                    'userId' => $stationnement->getUserId(),
                    'parkingId' => $stationnement->getParkingId(),
                    'entryTime' => $stationnement->getEntryTime()->format(\DateTime::ATOM),
                    'exitTime' => $stationnement->getExitTime()?->format(\DateTime::ATOM),
                    'status' => $stationnement->getStatus(),
                    'price' => $stationnement->getPrice()?->getAmount(),
                    'penaltyAmount' => $stationnement->getPenaltyAmount(),
                    'hasPenalty' => $stationnement->getHasPenalty(),
                    'totalAmount' => $stationnement->getTotalAmount(),
                    'durationMinutes' => $stationnement->getDurationInMinutes(),
                    'updatedAt' => $stationnement->getUpdatedAt()->format(\DateTime::ATOM)
                ]
            ];
        } catch (EntityNotFoundException $e) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
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
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/stationnements/{id}
     * Récupère un stationnement par son ID
     */
    public function show(string $id, ?string $userId = null): array
    {
        try {
            $stationnement = $this->getUseCase->execute($id, $userId);

            return [
                'status' => 'success',
                'data' => [
                    'id' => $stationnement->getId(),
                    'userId' => $stationnement->getUserId(),
                    'parkingId' => $stationnement->getParkingId(),
                    'entryTime' => $stationnement->getEntryTime()->format(\DateTime::ATOM),
                    'exitTime' => $stationnement->getExitTime()?->format(\DateTime::ATOM),
                    'status' => $stationnement->getStatus(),
                    'price' => $stationnement->getPrice()?->getAmount(),
                    'penaltyAmount' => $stationnement->getPenaltyAmount(),
                    'hasPenalty' => $stationnement->getHasPenalty(),
                    'totalAmount' => $stationnement->getTotalAmount(),
                    'durationMinutes' => $stationnement->getDurationInMinutes(),
                    'reservationId' => $stationnement->getReservationId(),
                    'abonnementId' => $stationnement->getAbonnementId(),
                    'createdAt' => $stationnement->getCreatedAt()->format(\DateTime::ATOM),
                    'updatedAt' => $stationnement->getUpdatedAt()->format(\DateTime::ATOM)
                ]
            ];
        } catch (EntityNotFoundException $e) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\DomainException $e) {
            http_response_code(403);
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

    /**
     * GET /api/stationnements?user_id=...
     * Liste les stationnements d'un utilisateur
     */
    public function index(?string $userId = null): array
    {
        try {
            if ($userId === null) {
                throw new \InvalidArgumentException('user_id parameter is required');
            }

            $stationnements = $this->stationnementRepository->findByUserId($userId);

            $data = array_map(function ($stationnement) {
                return [
                    'id' => $stationnement->getId(),
                    'parkingId' => $stationnement->getParkingId(),
                    'entryTime' => $stationnement->getEntryTime()->format(\DateTime::ATOM),
                    'exitTime' => $stationnement->getExitTime()?->format(\DateTime::ATOM),
                    'status' => $stationnement->getStatus(),
                    'price' => $stationnement->getPrice()?->getAmount(),
                    'hasPenalty' => $stationnement->getHasPenalty(),
                    'durationMinutes' => $stationnement->getDurationInMinutes()
                ];
            }, $stationnements);

            return [
                'status' => 'success',
                'data' => $data
            ];
        } catch (\InvalidArgumentException $e) {
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

