<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByRole(string $role): array;
    public function findAll(): array;
    public function create(User $user): ?User;
    public function update(User $user): ?User;
    public function delete(string $id): bool;
    public function save(User $user): ?User;
}