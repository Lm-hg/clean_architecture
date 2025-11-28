<?php

namespace App\Application\UseCases\Abonnement;

use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Entities\Abonnement;

class SubscribeToAbonnementUseCase
{
    private AbonnementRepositoryInterface $repo;

    public function __construct(AbonnementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Subscribe a user to an existing abonnement (mark as paid and active)
     */
    public function execute(string $abonnementId): ?Abonnement
    {
        $a = $this->repo->findById($abonnementId);
        if ($a === null) {
            return null;
        }

        // Mark as paid and persist (entity method markAsPaid exists)
        $a->markAsPaid();
        $saved = $this->repo->save($a);

        return $saved;
    }
}
