<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\ParkingOwner;

interface ParkingOwnerRepositoryInterface
{
    public function findById(string $id): ?ParkingOwner;
    public function findByEmail(string $email): ?ParkingOwner;
    public function findAll(): array;
    public function create(ParkingOwner $parkingOwner): ?ParkingOwner;
    public function update(ParkingOwner $parkingOwner): ?ParkingOwner;
    public function delete(string $id): bool;
    public function save(ParkingOwner $parkingOwner): ?ParkingOwner;
}