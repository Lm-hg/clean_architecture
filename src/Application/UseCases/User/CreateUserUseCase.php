<?php

namespace App\Application\UseCases\User;

use App\Application\dtos\user\CreateUserDto;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Entities\UserEntity;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ObjectValues\User\IdUser;
use App\Domain\ObjectValues\User\Email;
use App\Domain\ObjectValues\User\Role;
use DateTime;

class CreateUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(CreateUserDto $createUserDto): UserReponseDto {
        // Vérifier si l'email existe déjà
        $existingUser = $this->repository->findByEmail($createUserDto->email->getEmail());
        if ($existingUser !== null) {
            throw new \Exception("Email already exists: " . $createUserDto->email->getEmail());
        }
        
        // Générer un UUID v4 pour l'utilisateur
        $userId = $this->generateUuid();
        
        // Hasher le mot de passe avant de le stocker
        $hashedPassword = password_hash($createUserDto->password->getPlainPassword(), PASSWORD_DEFAULT);
        
        // Créer les dates au format string
        $now = new DateTime();
        $dateString = $now->format('Y-m-d H:i:s');
        
        // Créer l'entité User avec l'UUID généré
        // Les validations (rôle, prénom, nom, email) sont maintenant dans le constructeur de UserEntity
        $user = new UserEntity(
            $userId,
            $createUserDto->role->getRole(),
            $createUserDto->firstName,
            $createUserDto->name,
            $createUserDto->email->getEmail(),
            $hashedPassword,
            $dateString,
            $dateString
        );
        
        // Sauvegarder l'utilisateur
        $savedUser = $this->repository->create($user);
        
        // Vérifier que l'utilisateur a bien été créé
        if ($savedUser === null) {
            throw new \Exception("Failed to create user. Repository returned null.");
        }
        
        // Créer la réponse DTO avec les objets Value Objects
        return new UserReponseDto(
            new IdUser($savedUser->getId()),
            $savedUser->getFirstName(),
            $savedUser->getName(),
            new Email($savedUser->getEmail()),
            new Role($savedUser->getRole())
        );
    }
    
    /**
     * Génère un UUID v4 format simple
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 10
        
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}