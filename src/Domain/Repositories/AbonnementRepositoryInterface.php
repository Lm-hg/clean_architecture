<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Abonnement;

interface AbonnementRepositoryInterface
{
    public function save(Abonnement $abonnement): ?Abonnement;

    public function findById(string $id): ?Abonnement;

    /** @return Abonnement[] */
    public function findByUserId(string $userId): array;

    /** @return Abonnement[] */
    public function findActiveForParking(string $parkingId): array;
}
