<?php

namespace App\Application\UseCases\User;

use App\Application\dtos\user\UpdateUserDto;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Entities\UserEntity;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ObjectValues\User\IdUser;
use App\Domain\ObjectValues\User\Email;
use App\Domain\ObjectValues\User\Role;
use DateTime;

class UpdateUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $userId, UpdateUserDto $updateUserDto): UserReponseDto
    {
        // Vérifier que l'utilisateur existe
        $existingUser = $this->repository->findById($userId);
        
        if ($existingUser === null) {
            throw new \Exception("User not found.");
        }
        
        // Hasher le nouveau mot de passe s'il est fourni
        $hashedPassword = password_hash($updateUserDto->password->getPlainPassword(), PASSWORD_DEFAULT);
        
        // Créer les dates
        $now = new DateTime();
        $updatedAt = $now->format('Y-m-d H:i:s');
        
        // Créer l'entité User mise à jour (on garde l'email et la date de création originaux)
        // Les validations (rôle, prénom, nom) sont maintenant dans le constructeur de UserEntity
        $updatedUser = new UserEntity(
            $existingUser->getId(),
            $updateUserDto->role->getRole(),
            $updateUserDto->firstName,
            $updateUserDto->name,
            $existingUser->getEmail(), // L'email ne peut pas être modifié
            $hashedPassword,
            $existingUser->getCreatedAt(),
            $updatedAt
        );
        
        // Sauvegarder les modifications
        $savedUser = $this->repository->update($updatedUser);
        
        // Vérifier que l'utilisateur a bien été mis à jour
        if ($savedUser === null) {
            throw new \Exception("Failed to update user. Repository returned null.");
        }
        
        // Retourner le DTO de réponse
        return new UserReponseDto(
            new IdUser($savedUser->getId()),
            $savedUser->getFirstName(),
            $savedUser->getName(),
            new Email($savedUser->getEmail()),
            new Role($savedUser->getRole())
        );
    }
}

