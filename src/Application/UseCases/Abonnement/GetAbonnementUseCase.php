<?php

namespace App\Application\UseCases\Abonnement;

use App\Application\dtos\Abonnement\AbonnementResponseDto;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

class GetAbonnementUseCase
{
    private AbonnementRepositoryInterface $repo;

    public function __construct(AbonnementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function execute(string $id): AbonnementResponseDto
    {
        $a = $this->repo->findById($id);
        if ($a === null) {
            throw new NotFoundException("Abonnement not found: {$id}");
        }

        return new AbonnementResponseDto(
            $a->getId() ?? '',
            $a->getUserId(),
            $a->getParkingId(),
            $a->getType(),
            $a->getStartDate()->format(DATE_ATOM),
            $a->getEndDate()->format(DATE_ATOM),
            $a->getMonthlyPrice()
        );
    }
}
