<?php

namespace App\Application\UseCases\ParkingOwner;

use App\Application\dtos\parkingOwner\LoginParkingOwnerDto;
use App\Application\dtos\parkingOwner\ParkingOwnerResponseDto;
use App\Domain\Repositories\ParkingOwnerRepositoryInterface;
use App\Domain\Services\JwtServiceInterface;
use App\Domain\Exceptions\InvalidCredentialsException;

class LoginParkingOwnerUseCase
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

    public function execute(LoginParkingOwnerDto $loginDto): ParkingOwnerResponseDto
    {
        // Vérifier si le propriétaire de parking existe
        $parkingOwner = $this->parkingOwnerRepository->findByEmail($loginDto->email->getEmail());
        
        if ($parkingOwner === null) {
            throw new InvalidCredentialsException('Email ou mot de passe incorrect');
        }

        // Vérifier le mot de passe
        if (!password_verify($loginDto->password, $parkingOwner->getPasswordHash())) {
            throw new InvalidCredentialsException('Email ou mot de passe incorrect');
        }

        // Générer un token JWT
        $token = $this->jwtService->generate([
            'id' => $parkingOwner->getId(),
            'email' => $parkingOwner->getEmail()->getEmail(),
            'type' => 'parking_owner'
        ]);

        // Retourner la réponse avec le token
        return new ParkingOwnerResponseDto(
            id: $parkingOwner->getId(),
            firstName: $parkingOwner->getPrenom(),
            lastName: $parkingOwner->getNom(),
            email: $parkingOwner->getEmail(),
            createdAt: $parkingOwner->getCreatedAt(),
            updatedAt: $parkingOwner->getUpdatedAt(),
            token: $token
        );
    }
}