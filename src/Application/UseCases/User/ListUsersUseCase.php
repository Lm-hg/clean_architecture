<?php

namespace App\Application\UseCases\User;

use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ObjectValues\User\IdUser;
use App\Domain\ObjectValues\User\Email;
use App\Domain\ObjectValues\User\Role;

class ListUsersUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return UserReponseDto[]
     */
    public function execute(): array
    {
        $users = $this->repository->findAll();
        
        return array_map(function ($user) {
            return new UserReponseDto(
                new IdUser($user->getId()),
                $user->getFirstName(),
                $user->getName(),
                new Email($user->getEmail()),
                new Role($user->getRole())
            );
        }, $users);
    }
}

