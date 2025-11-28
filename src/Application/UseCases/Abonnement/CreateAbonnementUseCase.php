<?php

namespace App\Application\UseCases\Abonnement;

use App\Application\dtos\Abonnement\CreateAbonnementDto;
use App\Application\dtos\Abonnement\AbonnementResponseDto;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Entities\Abonnement;

class CreateAbonnementUseCase
{
    private AbonnementRepositoryInterface $repo;

    public function __construct(AbonnementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function execute(CreateAbonnementDto $dto): AbonnementResponseDto
    {
        $now = new \DateTime();

        // Create domain entity
        $ab = new Abonnement(
            $dto->userId,
            $dto->parkingId,
            $dto->type,
            $dto->timeSlots,
            $dto->startDate instanceof \DateTimeInterface ? new \DateTime($dto->startDate->format('Y-m-d H:i:s')) : new \DateTime($dto->startDate),
            $dto->endDate instanceof \DateTimeInterface ? new \DateTime($dto->endDate->format('Y-m-d H:i:s')) : new \DateTime($dto->endDate),
            $dto->monthlyPrice,
            $now,
            clone $now
        );

        $saved = $this->repo->save($ab);

        if ($saved === null) {
            throw new \RuntimeException('Failed to save abonnement');
        }

        return new AbonnementResponseDto(
            $saved->getId() ?? '',
            $saved->getUserId(),
            $saved->getParkingId(),
            $saved->getType(),
            $saved->getStartDate()->format(DATE_ATOM),
            $saved->getEndDate()->format(DATE_ATOM),
            $saved->getMonthlyPrice()
        );
    }
}
