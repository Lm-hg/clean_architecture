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
            // Validate required fields
            if (!isset($data['user_id'], $data['parking_id'], $data['start_time'], $data['end_time'])) {
                throw new \InvalidArgumentException('Missing required fields');
            }

            $start = new \DateTime($data['start_time']);
            $end = new \DateTime($data['end_time']);

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
                    'status' => $reservation->getStatus(),
                    'start_time' => $reservation->getStartTime()->format(\DateTime::ATOM),
                    'end_time' => $reservation->getEndTime()->format(\DateTime::ATOM),
                    'created_at' => $reservation->getCreatedAt()->format(\DateTime::ATOM)
                ]
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

        return [
            'status' => 'success',
            'data' => [
                'id' => $reservation->getId(),
                'user_id' => $reservation->getUserId(),
                'parking_id' => $reservation->getParkingId(),
                'status' => $reservation->getStatus(),
                'start_time' => $reservation->getStartTime()->format(\DateTime::ATOM),
                'end_time' => $reservation->getEndTime()->format(\DateTime::ATOM),
                'price' => $reservation->getPrice() ? $reservation->getPrice()->getAmount() : null,
                'is_paid' => $reservation->getIsPaid(),
                'created_at' => $reservation->getCreatedAt()->format(\DateTime::ATOM)
            ]
        ];
    }

    public function index(string $userId): array
    {
        $reservations = $this->listUseCase->execute($userId);

        $data = array_map(function ($reservation) {
            return [
                'id' => $reservation->getId(),
                'parking_id' => $reservation->getParkingId(),
                'status' => $reservation->getStatus(),
                'start_time' => $reservation->getStartTime()->format(\DateTime::ATOM),
                'end_time' => $reservation->getEndTime()->format(\DateTime::ATOM)
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

