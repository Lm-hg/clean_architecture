<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\ValueObjects\Price\Price;

class ReservationRepository implements ReservationRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Reservation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM reservations');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByParkingId(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE parking_id = :parking_id');
        $stmt->execute(['parking_id' => $parkingId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE status = :status');
        $stmt->execute(['status' => $status]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findReservationsInInterval(
        string $parkingId,
        \DateTime $start,
        \DateTime $end
    ): array {
        // On cherche les réservations qui chevauchent l'intervalle donné pour ce parking
        // condition: (StartA < EndB) and (EndA > StartB)
        $sql = '
            SELECT * FROM reservations 
            WHERE parking_id = :parking_id 
            AND start_time < :end_time 
            AND end_time > :start_time
            AND status != :status_cancelled
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'parking_id' => $parkingId,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'status_cancelled' => Reservation::STATUS_CANCELLED
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(Reservation $reservation): Reservation
    {
        $id = $reservation->getId();
        
        if ($id === null) {
            // Insert logic is handled via database default uuid generation if we pass null, 
            // but Reservation entity usually generates ID or expects one.
            // If the entity has null ID, we might need to let DB generate it and retrieve it,
            // or generate it in PHP.
            // Looking at init.sql: id UUID PRIMARY KEY DEFAULT uuid_generate_v4()
            // Looking at Reservation.php: id is optional in constructor.
            
            // Let's assume we let the DB generate ID if it's null, but we need to return the updated entity.
            // OR we generate UUID in PHP (better for DDD).
            // Since I don't have a UUID generator service handy, I'll use DB returning.
            
            $sql = '
                INSERT INTO reservations (
                    user_id, parking_id, start_time, end_time, status, 
                    total_price, payment_status, created_at, updated_at
                ) VALUES (
                    :user_id, :parking_id, :start_time, :end_time, :status, 
                    :total_price, :payment_status, :created_at, :updated_at
                ) RETURNING id
            ';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $reservation->getUserId(),
                'parking_id' => $reservation->getParkingId(),
                'start_time' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
                'end_time' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
                'status' => $reservation->getStatus(),
                'total_price' => $reservation->getPrice() ? $reservation->getPrice()->getAmount() : 0,
                'payment_status' => $reservation->getIsPaid() ? 'completed' : 'pending',
                'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $reservation->getUpdatedAt()->format('Y-m-d H:i:s')
            ]);
            
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $newId = $row['id'];
            
            // Reconstitute with new ID
            return Reservation::reconstitute(
                $newId,
                $reservation->getUserId(),
                $reservation->getParkingId(),
                $reservation->getStartTime(),
                $reservation->getEndTime(),
                $reservation->getStatus(),
                $reservation->getPrice(),
                $reservation->getIsPaid(),
                $reservation->getCreatedAt(),
                $reservation->getUpdatedAt()
            );

        } else {
            // Update
            $sql = '
                UPDATE reservations SET 
                    user_id = :user_id,
                    parking_id = :parking_id,
                    start_time = :start_time,
                    end_time = :end_time,
                    status = :status,
                    total_price = :total_price,
                    payment_status = :payment_status,
                    updated_at = :updated_at
                WHERE id = :id
            ';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'user_id' => $reservation->getUserId(),
                'parking_id' => $reservation->getParkingId(),
                'start_time' => $reservation->getStartTime()->format('Y-m-d H:i:s'),
                'end_time' => $reservation->getEndTime()->format('Y-m-d H:i:s'),
                'status' => $reservation->getStatus(),
                'total_price' => $reservation->getPrice() ? $reservation->getPrice()->getAmount() : 0,
                'payment_status' => $reservation->getIsPaid() ? 'completed' : 'pending',
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s') // Force update time
            ]);
            
            return $reservation;
        }
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reservations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Reservation
    {
        // Assuming 'total_price' column exists and is currency-agnostic or we default to EUR
        $price = isset($row['total_price']) ? Price::fromFloat((float)$row['total_price'], 'EUR') : null;
        
        return Reservation::reconstitute(
            $row['id'],
            $row['user_id'],
            $row['parking_id'], // Assuming UUID string
            new \DateTime($row['start_time']),
            new \DateTime($row['end_time']),
            $row['status'],
            $price,
            ($row['payment_status'] === 'completed'),
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at'])
        );
    }
}
