<?php

namespace App\Application\UseCases\User;

use App\Domain\Repositories\UserRepositoryInterface;

class DeleteUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $userId): bool
    {
        // Vérifier que l'utilisateur existe
        $user = $this->repository->findById($userId);
        
        if ($user === null) {
            throw new \Exception("User not found.");
        }
        
        // Supprimer l'utilisateur
        return $this->repository->delete($userId);
    }
}

