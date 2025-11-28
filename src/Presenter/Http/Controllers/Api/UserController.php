<?php

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\UseCases\User\GetUserUseCase;
use App\Application\UseCases\User\UpdateUserUseCase;
use App\Application\UseCases\User\DeleteUserUseCase;
use App\Application\UseCases\User\ListUsersUseCase;
use App\Application\dtos\user\CreateUserDto;
use App\Application\dtos\user\UpdateUserDto;
use App\Domain\ValueObjects\User\Email;
use App\Domain\ValueObjects\User\Password;
use App\Domain\ValueObjects\User\Role;

class UserController
{
    private CreateUserUseCase $createUserUseCase;
    private GetUserUseCase $getUserUseCase;
    private UpdateUserUseCase $updateUserUseCase;
    private DeleteUserUseCase $deleteUserUseCase;
    private ListUsersUseCase $listUsersUseCase;

    public function __construct(
        CreateUserUseCase $createUserUseCase,
        GetUserUseCase $getUserUseCase,
        UpdateUserUseCase $updateUserUseCase,
        DeleteUserUseCase $deleteUserUseCase,
        ListUsersUseCase $listUsersUseCase
    ) {
        $this->createUserUseCase = $createUserUseCase;
        $this->getUserUseCase = $getUserUseCase;
        $this->updateUserUseCase = $updateUserUseCase;
        $this->deleteUserUseCase = $deleteUserUseCase;
        $this->listUsersUseCase = $listUsersUseCase;
    }

    /**
     * POST /api/users
     * Créer un nouvel utilisateur
     */
    public function create(array $requestData): array
    {
        try {
            // Valider les données d'entrée
            $this->validateCreateRequest($requestData);

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
                'data' => $this->formatUserResponse($userResponse),
                'message' => 'User created successfully'
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

    /**
     * GET /api/users/{id}
     * Récupérer un utilisateur par ID
     */
    public function show(string $id): array
    {
        try {
            $userResponse = $this->getUserUseCase->execute($id);

            if ($userResponse === null) {
                http_response_code(404);
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }

            return [
                'status' => 'success',
                'data' => $this->formatUserResponse($userResponse)
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
     * PUT /api/users/{id}
     * Mettre à jour un utilisateur
     */
    public function update(string $id, array $requestData): array
    {
        try {
            // Valider les données d'entrée
            $this->validateUpdateRequest($requestData);

            // Créer le DTO
            $updateUserDto = new UpdateUserDto(
                $requestData['firstName'],
                $requestData['name'],
                new Password($requestData['password']),
                new Role($requestData['role'] ?? 'user')
            );

            // Exécuter le use case
            $userResponse = $this->updateUserUseCase->execute($id, $updateUserDto);

            return [
                'status' => 'success',
                'data' => $this->formatUserResponse($userResponse),
                'message' => 'User updated successfully'
            ];
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            $code = strpos($e->getMessage(), 'not found') !== false ? 404 : 500;
            http_response_code($code);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * DELETE /api/users/{id}
     * Supprimer un utilisateur
     */
    public function delete(string $id): array
    {
        try {
            $success = $this->deleteUserUseCase->execute($id);

            if (!$success) {
                http_response_code(500);
                return [
                    'status' => 'error',
                    'message' => 'Failed to delete user'
                ];
            }

            return [
                'status' => 'success',
                'message' => 'User deleted successfully'
            ];
        } catch (\Exception $e) {
            $code = strpos($e->getMessage(), 'not found') !== false ? 404 : 500;
            http_response_code($code);
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/users
     * Lister tous les utilisateurs
     */
    public function index(): array
    {
        try {
            $users = $this->listUsersUseCase->execute();

            return [
                'status' => 'success',
                'data' => array_map([$this, 'formatUserResponse'], $users),
                'count' => count($users)
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
     * Formate une UserReponseDto en tableau pour la réponse JSON
     */
    private function formatUserResponse($userResponse): array
    {
        return [
            'id' => $userResponse->id->getId(),
            'firstName' => $userResponse->firstName,
            'name' => $userResponse->name,
            'email' => $userResponse->email->getEmail(),
            'role' => $userResponse->role->getRole()
        ];
    }

    /**
     * Valide les données pour la création d'utilisateur
     */
    private function validateCreateRequest(array $data): void
    {
        if (empty($data['firstName'])) {
            throw new \InvalidArgumentException('First name is required');
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }

        if (empty($data['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }

        if (empty($data['password'])) {
            throw new \InvalidArgumentException('Password is required');
        }

        if (isset($data['role']) && !in_array($data['role'], ['admin', 'user', 'ownerParking'])) {
            throw new \InvalidArgumentException('Invalid role. Allowed roles: admin, user, ownerParking');
        }
    }

    /**
     * Valide les données pour la mise à jour d'utilisateur
     */
    private function validateUpdateRequest(array $data): void
    {
        if (empty($data['firstName'])) {
            throw new \InvalidArgumentException('First name is required');
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }

        if (empty($data['password'])) {
            throw new \InvalidArgumentException('Password is required');
        }

        if (isset($data['role']) && !in_array($data['role'], ['admin', 'user', 'ownerParking'])) {
            throw new \InvalidArgumentException('Invalid role. Allowed roles: admin, user, ownerParking');
        }
    }
}

