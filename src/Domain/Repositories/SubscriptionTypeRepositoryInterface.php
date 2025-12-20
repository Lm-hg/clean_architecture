<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SubscriptionType;

interface SubscriptionTypeRepositoryInterface
{
    public function save(SubscriptionType $subscriptionType): ?SubscriptionType;
    
    public function findById(string $id): ?SubscriptionType;
    
    public function findByParkingId(string $parkingId): array;
    
    public function findActiveByParkingId(string $parkingId): array;
    
    public function delete(string $id): bool;
}
