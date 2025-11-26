<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\UserEntity;

interface UserRepositoryInterface
{
    public function findById(string $id): ?UserEntity;
    public function findByEmail(string $email): ?UserEntity;
    public function findByRole(string $role): array;
    public function findAll(): array;
    public function create(UserEntity $user): ?UserEntity;
    public function update(UserEntity $user): ?UserEntity;
    public function delete(string $id): bool;
    public function save(UserEntity $user): ?UserEntity;
}