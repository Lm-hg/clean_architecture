<?php

namespace App\Application\UseCases\Auth;

use App\Application\dtos\auth\LoginDto;
use App\Application\dtos\auth\LoginResponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Services\JwtServiceInterface;
use App\Domain\ValueObjects\User\IdUser;
use App\Domain\ValueObjects\User\Email;
use App\Domain\ValueObjects\User\Role;

class LoginUserUseCase
{
    private UserRepositoryInterface $userRepository;
    private JwtServiceInterface $jwtService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        JwtServiceInterface $jwtService
    ) {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
    }

    public function execute(LoginDto $loginDto): LoginResponseDto
    {
        // Vérifier si l'utilisateur existe
        $user = $this->userRepository->findByEmail($loginDto->email->getEmail());
        
        if ($user === null) {
            throw new \InvalidArgumentException("Invalid email or password.");
        }

        // Vérifier le mot de passe
        if (!password_verify($loginDto->password, $user->getPassword())) {
            throw new \InvalidArgumentException("Invalid email or password.");
        }

        // Générer le token JWT
        $token = $this->jwtService->generateToken(
            $user->getId(),
            $user->getEmail(),
            $user->getRole()
        );

        // Créer et retourner la réponse
        return new LoginResponseDto(
            new IdUser($user->getId()),
            $user->getFirstName(),
            $user->getName(),
            new Email($user->getEmail()),
            new Role($user->getRole()),
            $token
        );
    }
}

