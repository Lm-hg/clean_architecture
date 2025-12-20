<?php
declare(strict_types=1);

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Abonnement\GetSubscriptionTypesUseCase;
use App\Application\UseCases\Abonnement\CreateUserSubscriptionUseCase;
use App\Application\UseCases\Abonnement\GetUserSubscriptionsUseCase;
use App\Application\UseCases\Abonnement\CancelUserSubscriptionUseCase;
use App\Domain\Exceptions\EntityNotFoundException;

/**
 * Controller pour la gestion des abonnements utilisateurs
 * Respecte la Clean Architecture en appelant uniquement des Use Cases
 */
class UserSubscriptionController
{
    private GetSubscriptionTypesUseCase $getTypesUseCase;
    private CreateUserSubscriptionUseCase $createSubscriptionUseCase;
    private GetUserSubscriptionsUseCase $getUserSubscriptionsUseCase;
    private ?CancelUserSubscriptionUseCase $cancelSubscriptionUseCase;

    public function __construct(
        GetSubscriptionTypesUseCase $getTypesUseCase,
        CreateUserSubscriptionUseCase $createSubscriptionUseCase,
        GetUserSubscriptionsUseCase $getUserSubscriptionsUseCase,
        ?CancelUserSubscriptionUseCase $cancelSubscriptionUseCase = null
    ) {
        $this->getTypesUseCase = $getTypesUseCase;
        $this->createSubscriptionUseCase = $createSubscriptionUseCase;
        $this->getUserSubscriptionsUseCase = $getUserSubscriptionsUseCase;
        $this->cancelSubscriptionUseCase = $cancelSubscriptionUseCase;
    }

    /**
     * GET /api/parkings/{parkingId}/abonnements
     * Récupère les types d'abonnements disponibles pour un parking
     */
    public function getAvailableTypes(string $parkingId): array
    {
        try {
            $types = $this->getTypesUseCase->execute($parkingId);
            
            $formatted = array_map(function($type) {
                return [
                    'id' => $type->getId(),
                    'parkingId' => $type->getParkingId(),
                    'name' => $type->getName(),
                    'description' => $type->getDescription(),
                    'price' => $type->getPrice()->getAmount(),
                    'durationDays' => $type->getDurationDays(),
                    'benefits' => $type->getBenefits(),
                    'timeSlots' => array_map(function($slot) {
                        return $slot->toArray();
                    }, $type->getTimeSlots())
                ];
            }, $types);

            return [
                'status' => 'success',
                'data' => $formatted,
                'message' => 'Subscription types retrieved successfully'
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Failed to retrieve subscription types: ' . $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/abonnements
     * Crée un nouvel abonnement pour l'utilisateur connecté
     */
    public function createSubscription(string $userId, array $requestData): array
    {
        try {
            // Validation
            if (empty($requestData['subscriptionTypeId'])) {
                http_response_code(400);
                return [
                    'status' => 'error',
                    'message' => 'subscriptionTypeId is required'
                ];
            }

            $startDate = isset($requestData['startDate']) 
                ? new \DateTime($requestData['startDate']) 
                : null;

            // Appel du Use Case
            $subscription = $this->createSubscriptionUseCase->execute(
                $userId,
                $requestData['subscriptionTypeId'],
                $startDate
            );

            return [
                'status' => 'success',
                'message' => 'Subscription created successfully',
                'data' => [
                    'id' => $subscription->getId(),
                    'userId' => $subscription->getUserId(),
                    'parkingId' => $subscription->getParkingId(),
                    'subscriptionType' => $subscription->getType(),
                    'startDate' => $subscription->getStartDate()->format(\DateTime::ATOM),
                    'endDate' => $subscription->getEndDate()->format(\DateTime::ATOM),
                    'status' => $subscription->getStatus(),
                    'price' => $subscription->getMonthlyPrice()->getAmount()
                ]
            ];

        } catch (EntityNotFoundException $e) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\DomainException $e) {
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Failed to create subscription: ' . $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/user/subscriptions
     * Récupère les abonnements de l'utilisateur connecté
     */
    public function getUserSubscriptions(string $userId): array
    {
        try {
            $subscriptions = $this->getUserSubscriptionsUseCase->execute($userId);

            $formatted = array_map(function($sub) {
                return [
                    'id' => $sub->getId(),
                    'userId' => $sub->getUserId(),
                    'parkingId' => $sub->getParkingId(),
                    'subscriptionType' => $sub->getType(),
                    'startDate' => $sub->getStartDate()->format(\DateTime::ATOM),
                    'endDate' => $sub->getEndDate()->format(\DateTime::ATOM),
                    'status' => $sub->getStatus(),
                    'price' => $sub->getMonthlyPrice()->getAmount(),
                    'timeSlots' => array_map(function($slot) {
                        return $slot->toArray();
                    }, $sub->getTimeSlots())
                ];
            }, $subscriptions);

            return [
                'status' => 'success',
                'data' => $formatted,
                'message' => 'Subscriptions retrieved successfully'
            ];

        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Failed to retrieve subscriptions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * PUT /api/user/subscriptions/{subscriptionId}/cancel
     * Annule un abonnement de l'utilisateur connecté
     */
    public function cancelSubscription(string $userId, string $subscriptionId): array
    {
        try {
            if ($this->cancelSubscriptionUseCase === null) {
                throw new \RuntimeException("Cancel subscription use case not configured");
            }

            $subscription = $this->cancelSubscriptionUseCase->execute($subscriptionId, $userId);

            return [
                'status' => 'success',
                'message' => 'Subscription cancelled successfully',
                'data' => [
                    'id' => $subscription->getId(),
                    'status' => $subscription->getStatus()
                ]
            ];

        } catch (EntityNotFoundException $e) {
            http_response_code(404);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\DomainException $e) {
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ];
        }
    }
}
