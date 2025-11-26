<?php

namespace Tests\Functional\User;

use PDO;

/**
 * Helper pour faciliter les tests fonctionnels en appelant directement les controllers
 * au lieu de simuler les requêtes HTTP
 */
class UserApiFunctionalTestHelper
{
    private PDO $pdo;
    private string $jwtSecretKey;

    public function __construct(PDO $pdo, string $jwtSecretKey = 'your-secret-key-change-in-production')
    {
        $this->pdo = $pdo;
        $this->jwtSecretKey = $jwtSecretKey;
    }

    /**
     * Crée une instance du AuthController
     */
    public function createAuthController(): \App\Presenter\Http\Controllers\Api\AuthController
    {
        $userRepository = new \App\Infrastructure\Persistence\Sql\UserRepository($this->pdo);
        $jwtService = new \App\Infrastructure\Services\JwtService($this->jwtSecretKey);
        $loginUserUseCase = new \App\Application\UseCases\Auth\LoginUserUseCase($userRepository, $jwtService);
        $createUserUseCase = new \App\Application\UseCases\User\CreateUserUseCase($userRepository);
        
        return new \App\Presenter\Http\Controllers\Api\AuthController(
            $loginUserUseCase,
            $createUserUseCase
        );
    }

    /**
     * Crée une instance du UserController
     */
    public function createUserController(): \App\Presenter\Http\Controllers\Api\UserController
    {
        $userRepository = new \App\Infrastructure\Persistence\Sql\UserRepository($this->pdo);
        
        $createUserUseCase = new \App\Application\UseCases\User\CreateUserUseCase($userRepository);
        $getUserUseCase = new \App\Application\UseCases\User\GetUserUseCase($userRepository);
        $updateUserUseCase = new \App\Application\UseCases\User\UpdateUserUseCase($userRepository);
        $deleteUserUseCase = new \App\Application\UseCases\User\DeleteUserUseCase($userRepository);
        $listUsersUseCase = new \App\Application\UseCases\User\ListUsersUseCase($userRepository);
        
        return new \App\Presenter\Http\Controllers\Api\UserController(
            $createUserUseCase,
            $getUserUseCase,
            $updateUserUseCase,
            $deleteUserUseCase,
            $listUsersUseCase
        );
    }

    /**
     * Crée une instance du middleware d'authentification
     */
    public function createAuthMiddleware(): \App\Presenter\Http\Middleware\AuthenticationMiddleware
    {
        $jwtService = new \App\Infrastructure\Services\JwtService($this->jwtSecretKey);
        return new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
    }
}

