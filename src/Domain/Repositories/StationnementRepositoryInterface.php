<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Stationnement;

interface StationnementRepositoryInterface
{
    /**
     * Retourne le stationnement correspondant à l'ID, ou null si non trouvé.
     */
    public function findById(string $id): ?Stationnement;

    /**
     * Retourne tous les stationnements.
     *
     * @return Stationnement[]
     */
    public function findAll(): array;

    /**
     * Retourne les stationnements d'un utilisateur donné.
     *
     * @return Stationnement[]
     */
    public function findByUserId(string $userId): array;

    /**
     * Retourne les stationnements pour un parking donné.
     *
     * @return Stationnement[]
     */
    public function findByParkingId(string $parkingId): array;

    /**
     * Retourne les stationnements actifs (sans heure de sortie) pour un parking donné.
     *
     * @return Stationnement[]
     */
    public function findActiveByParkingId(string $parkingId): array;

    /**
     * Retourne les stationnements actifs d'un utilisateur.
     *
     * @return Stationnement[]
     */
    public function findActiveByUserId(string $userId): array;

    /**
     * Retourne les stationnements liés à une réservation.
     *
     * @return Stationnement[]
     */
    public function findByReservationId(string $reservationId): array;

    /**
     * Retourne les stationnements liés à un abonnement.
     *
     * @return Stationnement[]
     */
    public function findByAbonnementId(string $abonnementId): array;

    /**
     * Sauvegarde un stationnement : insert ou update selon l'état de l'entité.
     *
     * @return Stationnement The saved stationnement (with updated properties like id, timestamps…)
     */
    public function save(Stationnement $stationnement): Stationnement;

    /**
     * Supprime le stationnement d'après son identifiant.
     *
     * @return bool True si la suppression a réussi, false sinon.
     */
    public function delete(string $id): bool;
}

