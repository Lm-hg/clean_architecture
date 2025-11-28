<?php

namespace App\Application\UseCases\Abonnement;

use App\Application\dtos\Abonnement\AbonnementResponseDto;
use App\Domain\Repositories\AbonnementRepositoryInterface;

class ListAbonnementsForParkingUseCase
{
    private AbonnementRepositoryInterface $repo;

    public function __construct(AbonnementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /** @return AbonnementResponseDto[] */
    public function execute(string $parkingId): array
    {
        $items = $this->repo->findActiveForParking($parkingId);
        $result = [];
        foreach ($items as $a) {
            $result[] = new AbonnementResponseDto(
                $a->getId() ?? '',
                $a->getUserId(),
                $a->getParkingId(),
                $a->getType(),
                $a->getStartDate()->format(DATE_ATOM),
                $a->getEndDate()->format(DATE_ATOM),
                $a->getMonthlyPrice()
            );
        }
        return $result;
    }
}
