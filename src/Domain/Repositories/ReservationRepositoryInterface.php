<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Reservation;

interface ReservationRepositoryInterface
{
    public function findById(string $id): ?Reservation;

    /**
     * @return Reservation[]
     */
    public function findAll(): array;

    /**
     * @return Reservation[]
     */
    public function findByUserId(string $userId): array;

    /**
     * @return Reservation[]
     */
    public function findByParkingId(string $parkingId): array;

    /**
     * Récupère les réservations actives ou prévues dans un intervalle de temps donné.
     * Cette méthode est purement technique (accès données) et ne porte pas de règle métier "chevauchement".
     * 
     * @param string $parkingId
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Reservation[]
     */
    public function findReservationsInInterval(
        string $parkingId,
        \DateTime $start,
        \DateTime $end
    ): array;

    public function save(Reservation $reservation): Reservation;

    public function delete(string $id): bool;
}
