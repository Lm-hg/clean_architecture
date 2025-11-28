<?php

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Abonnement\CreateAbonnementUseCase;
use App\Application\UseCases\Abonnement\GetAbonnementUseCase;
use App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase;
use App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase;
use App\Application\UseCases\Abonnement\ValidateAbonnementUseCase;
use App\Application\dtos\Abonnement\CreateAbonnementDto;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Pricing\Price;
use App\Domain\Exceptions\NotFoundException;

class AbonnementController
{
    private CreateAbonnementUseCase $createUseCase;
    private GetAbonnementUseCase $getUseCase;
    private ListAbonnementsForParkingUseCase $listUseCase;
    private SubscribeToAbonnementUseCase $subscribeUseCase;
    private ValidateAbonnementUseCase $validateUseCase;

    public function __construct(
        CreateAbonnementUseCase $createUseCase,
        GetAbonnementUseCase $getUseCase,
        ListAbonnementsForParkingUseCase $listUseCase,
        SubscribeToAbonnementUseCase $subscribeUseCase,
        ValidateAbonnementUseCase $validateUseCase
    ) {
        $this->createUseCase = $createUseCase;
        $this->getUseCase = $getUseCase;
        $this->listUseCase = $listUseCase;
        $this->subscribeUseCase = $subscribeUseCase;
        $this->validateUseCase = $validateUseCase;
    }

    /**
     * POST /api/abonnements
     */
    public function create(array $requestData): array
    {
        try {
            // Basic validation
            if (empty($requestData['userId']) || empty($requestData['parkingId']) || empty($requestData['type']) || empty($requestData['startDate']) || empty($requestData['endDate']) || !isset($requestData['monthlyPrice'])) {
                throw new \InvalidArgumentException('Missing required fields');
            }

            // Build TimeSlot objects if provided
            $timeSlots = [];
            if (!empty($requestData['timeSlots']) && is_array($requestData['timeSlots'])) {
                foreach ($requestData['timeSlots'] as $s) {
                    $timeSlots[] = new TimeSlot((int)$s['startDay'], (int)$s['startMinute'], (int)$s['endDay'], (int)$s['endMinute']);
                }
            }

            $start = $requestData['startDate'] instanceof \DateTimeInterface ? $requestData['startDate'] : new \DateTime($requestData['startDate']);
            $end = $requestData['endDate'] instanceof \DateTimeInterface ? $requestData['endDate'] : new \DateTime($requestData['endDate']);

            $price = is_numeric($requestData['monthlyPrice']) ? Price::fromFloat((float)$requestData['monthlyPrice']) : Price::fromFloat(floatval($requestData['monthlyPrice']));

            $dto = new CreateAbonnementDto(
                $requestData['userId'],
                $requestData['parkingId'],
                $requestData['type'],
                $timeSlots,
                $start,
                $end,
                $price
            );

            $resp = $this->createUseCase->execute($dto);

            return [
                'status' => 'success',
                'data' => $this->formatResponse($resp)
            ];
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * GET /api/abonnements/{id}
     */
    public function show(string $id): array
    {
        try {
            $resp = $this->getUseCase->execute($id);
            return ['status' => 'success', 'data' => $this->formatResponse($resp)];
        } catch (NotFoundException $e) {
            http_response_code(404);
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * GET /api/parkings/{parkingId}/abonnements
     */
    public function indexForParking(string $parkingId): array
    {
        try {
            $items = $this->listUseCase->execute($parkingId);
            return ['status' => 'success', 'data' => array_map([$this, 'formatResponse'], $items), 'count' => count($items)];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST /api/abonnements/{id}/subscribe
     */
    public function subscribe(string $id): array
    {
        try {
            $a = $this->subscribeUseCase->execute($id);
            if ($a === null) {
                http_response_code(404);
                return ['status' => 'error', 'message' => 'Abonnement not found'];
            }
            return ['status' => 'success', 'message' => 'Subscribed', 'data' => ['id' => $a->getId()]];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST /api/abonnements/{id}/validate
     * body: { "dateTime": "2025-11-28T12:00:00+00:00" }
     */
    public function validate(string $id, array $requestData): array
    {
        try {
            if (empty($requestData['dateTime'])) {
                throw new \InvalidArgumentException('dateTime is required');
            }
            $dt = $requestData['dateTime'] instanceof \DateTimeInterface ? $requestData['dateTime'] : new \DateTime($requestData['dateTime']);
            $ok = $this->validateUseCase->execute($id, $dt);
            return ['status' => 'success', 'data' => ['valid' => $ok]];
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function formatResponse($dto): array
    {
        return [
            'id' => $dto->id,
            'userId' => $dto->userId,
            'parkingId' => $dto->parkingId,
            'type' => $dto->type,
            'startDate' => $dto->startDate,
            'endDate' => $dto->endDate,
            'monthlyPrice' => $dto->monthlyPrice->__toString()
        ];
    }
}
