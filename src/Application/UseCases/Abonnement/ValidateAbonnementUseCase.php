<?php

namespace App\Application\UseCases\Abonnement;

use App\Domain\Repositories\AbonnementRepositoryInterface;

class ValidateAbonnementUseCase
{
    private AbonnementRepositoryInterface $repo;

    public function __construct(AbonnementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Validate whether an abonnement covers a given datetime for a parking/user context.
     * Returns true if abonnement exists and covers the datetime.
     */
    public function execute(string $abonnementId, \DateTimeInterface $dt): bool
    {
        $a = $this->repo->findById($abonnementId);
        if ($a === null) {
            return false;
        }

        if (!$a->isActive()) {
            return false;
        }

        return $a->coversDateTime($dt);
    }
}
