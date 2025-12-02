<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Parking;

interface ParkingRepositoryInterface
{
    public function findById(string $id): ?Parking;
    public function findByOwnerId(string $ownerId): array;
    public function findAll(): array;
    public function findAvailableParkings(): array;
    public function create(Parking $parking): ?Parking;
    public function update(Parking $parking): ?Parking;
    public function delete(string $id): bool;
    public function save(Parking $parking): ?Parking;
    public function findByLocation(float $lat, float $lng, float $radius): array;
    public function countAvailableSpots(string $parkingId): int;
}