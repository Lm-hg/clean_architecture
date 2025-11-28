<?php

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\Auth\LoginUserUseCase;
use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\dtos\auth\LoginDto;
use App\Application\dtos\user\CreateUserDto;
use App\Domain\ValueObjects\User\Email;
use App\Domain\ValueObjects\User\Password;
use App\Domain\ValueObjects\User\Role;

class AuthController
{
    private LoginUserUseCase $loginUserUseCase;
    private CreateUserUseCase $createUserUseCase;

    public function __construct(
        LoginUserUseCase $loginUserUseCase,
        CreateUserUseCase $createUserUseCase
    ) {
        $this->loginUserUseCase = $loginUserUseCase;
        $this->createUserUseCase = $createUserUseCase;
    }

    /**
     * POST /api/auth/login
     * Authentifie un utilisateur et retourne un token JWT
     */
    public function login(array $requestData): array
    {
        try {
            // Valider les données d'entrée
            if (empty($requestData['email'])) {
                throw new \InvalidArgumentException('Email is required');
            }

            if (empty($requestData['password'])) {
                throw new \InvalidArgumentException('Password is required');
            }

            // Créer le DTO
            $loginDto = new LoginDto(
                new Email($requestData['email']),
                $requestData['password']
            );

            // Exécuter le use case
            $loginResponse = $this->loginUserUseCase->execute($loginDto);

            // Retourner la réponse
            return [
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $loginResponse->id->getId(),
                        'firstName' => $loginResponse->firstName,
                        'name' => $loginResponse->name,
                        'email' => $loginResponse->email->getEmail(),
                        'role' => $loginResponse->role->getRole()
                    ],
                    'token' => $loginResponse->token
                ],
                'message' => 'Login successful'
            ];
        } catch (\InvalidArgumentException $e) {
            http_response_code(401);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/auth/register
     * Crée un nouveau compte utilisateur
     */
    public function register(array $requestData): array
    {
        try {
            // Valider les données d'entrée
            if (empty($requestData['firstName'])) {
                throw new \InvalidArgumentException('First name is required');
            }

            if (empty($requestData['name'])) {
                throw new \InvalidArgumentException('Name is required');
            }

            if (empty($requestData['email'])) {
                throw new \InvalidArgumentException('Email is required');
            }

            if (empty($requestData['password'])) {
                throw new \InvalidArgumentException('Password is required');
            }

            // Créer le DTO
            $createUserDto = new CreateUserDto(
                $requestData['firstName'],
                $requestData['name'],
                new Email($requestData['email']),
                new Password($requestData['password']),
                new Role($requestData['role'] ?? 'user')
            );

            // Exécuter le use case
            $userResponse = $this->createUserUseCase->execute($createUserDto);

            // Retourner la réponse
            return [
                'status' => 'success',
                'data' => [
                    'id' => $userResponse->id->getId(),
                    'firstName' => $userResponse->firstName,
                    'name' => $userResponse->name,
                    'email' => $userResponse->email->getEmail(),
                    'role' => $userResponse->role->getRole()
                ],
                'message' => 'User registered successfully'
            ];
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            http_response_code(500);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

