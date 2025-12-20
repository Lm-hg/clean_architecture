<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Application\dtos\parkingOwner\RegisterParkingOwnerDto;
use App\Application\dtos\parkingOwner\ParkingOwnerResponseDto;
use App\Domain\Entities\ParkingOwner;
use App\Domain\Repositories\ParkingOwnerRepositoryInterface;
use App\Domain\Services\JwtServiceInterface;
use App\Domain\Exceptions\DuplicateEmailException;

class RegisterParkingOwnerUseCase
{
    private ParkingOwnerRepositoryInterface $parkingOwnerRepository;
    private JwtServiceInterface $jwtService;

    public function __construct(
        ParkingOwnerRepositoryInterface $parkingOwnerRepository,
        JwtServiceInterface $jwtService
    ) {
        $this->parkingOwnerRepository = $parkingOwnerRepository;
        $this->jwtService = $jwtService;
    }

    public function execute(RegisterParkingOwnerDto $registerDto): ParkingOwnerResponseDto
    {
        // Vérifier si l'email n'est pas déjà utilisé
        $existingParkingOwner = $this->parkingOwnerRepository->findByEmail($registerDto->email->getEmail());
        if ($existingParkingOwner !== null) {
            throw new DuplicateEmailException('Un propriétaire de parking avec cet email existe déjà');
        }

        // Hasher le mot de passe
        $passwordHash = password_hash($registerDto->password, PASSWORD_BCRYPT);

        // Créer l'entité ParkingOwner
        $now = new \DateTime();
        $parkingOwner = new ParkingOwner(
            email: $registerDto->email,
            passwordHash: $passwordHash,
            nom: $registerDto->lastName,
            prenom: $registerDto->firstName,
            createdAt: $now,
            updatedAt: $now
        );

        // Sauvegarder en base de données
        $savedParkingOwner = $this->parkingOwnerRepository->save($parkingOwner);

        if ($savedParkingOwner === null) {
            throw new \RuntimeException('Erreur lors de la création du propriétaire de parking');
        }

        // Générer un token JWT
        $token = $this->jwtService->generate([
            'id' => $savedParkingOwner->getId(),
            'email' => $savedParkingOwner->getEmail()->getEmail(),
            'type' => 'parking_owner'
        ]);

        // Retourner la réponse
        return new ParkingOwnerResponseDto(
            id: $savedParkingOwner->getId(),
            firstName: $savedParkingOwner->getPrenom(),
            lastName: $savedParkingOwner->getNom(),
            email: $savedParkingOwner->getEmail(),
            createdAt: $savedParkingOwner->getCreatedAt(),
            updatedAt: $savedParkingOwner->getUpdatedAt(),
            token: $token
        );
    }
}