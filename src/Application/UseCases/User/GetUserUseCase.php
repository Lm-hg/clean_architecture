<?php

namespace App\Application\UseCases\User;

use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ObjectValues\User\IdUser;
use App\Domain\ObjectValues\User\Email;
use App\Domain\ObjectValues\User\Role;

class GetUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(string $userId): ?UserReponseDto
    {
        $user = $this->repository->findById($userId);
        
        if ($user === null) {
            return null;
        }
        
        return new UserReponseDto(
            new IdUser($user->getId()),
            $user->getFirstName(),
            $user->getName(),
            new Email($user->getEmail()),
            new Role($user->getRole())
        );
    }
}

