<?php

declare(strict_types=1);

namespace Infrastructure\NoSql;

use Infrastructure\NoSql\JsonFileService;

/**
 * Time Slot Repository - JSON Implementation
 * Handles subscription time slots and flexible scheduling
 * Clean Architecture - Infrastructure Layer
 */
class TimeSlotRepository
{
    private JsonFileService $jsonService;
    private string $collection = 'subscription_time_slots';

    public function __construct(JsonFileService $jsonService)
    {
        $this->jsonService = $jsonService;
    }

    /**
     * Save time slots for a subscription
     */
    public function saveTimeSlots(string $subscriptionId, array $timeSlots): string
    {
        $document = [
            'subscription_id' => $subscriptionId,
            'time_slots' => $timeSlots,
            'timezone' => 'Europe/Paris',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        return $this->jsonService->insertOne($this->collection, $document);
    }

    /**
     * Get time slots by subscription ID
     */
    public function getTimeSlotsBySubscriptionId(string $subscriptionId): ?array
    {
        return $this->jsonService->findOne($this->collection, ['subscription_id' => $subscriptionId]);
    }

    /**
     * Update time slots
     */
    public function updateTimeSlots(string $subscriptionId, array $timeSlots): bool
    {
        $filter = ['subscription_id' => $subscriptionId];
        $update = [
            'time_slots' => $timeSlots,
            'updated_at' => date('c'),
        ];

        return $this->jsonService->updateOne($this->collection, $filter, $update);
    }

    /**
     * Delete time slots
     */
    public function deleteTimeSlots(string $subscriptionId): bool
    {
        return $this->jsonService->deleteOne($this->collection, ['subscription_id' => $subscriptionId]);
    }

    /**
     * Check if time slot is available for subscription
     */
    public function isTimeSlotAvailable(string $subscriptionId, array $requestedSlot): bool
    {
        $timeSlots = $this->getTimeSlotsBySubscriptionId($subscriptionId);
        
        if (!$timeSlots) {
            return false;
        }

        foreach ($timeSlots['time_slots'] as $slot) {
            if ($this->slotsOverlap($slot, $requestedSlot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all time slots for a parking (multiple subscriptions)
     */
    public function getTimeSlotsForParking(array $subscriptionIds): array
    {
        $allSlots = [];
        
        foreach ($subscriptionIds as $subscriptionId) {
            $slots = $this->getTimeSlotsBySubscriptionId($subscriptionId);
            if ($slots) {
                $allSlots[$subscriptionId] = $slots;
            }
        }

        return $allSlots;
    }

    /**
     * Check if two time slots overlap
     */
    private function slotsOverlap(array $slot1, array $slot2): bool
    {
        if ($slot1['day'] !== $slot2['day']) {
            return false;
        }

        $start1 = strtotime($slot1['start']);
        $end1 = strtotime($slot1['end']);
        $start2 = strtotime($slot2['start']);
        $end2 = strtotime($slot2['end']);

        return ($start1 < $end2) && ($end1 > $start2);
    }
}