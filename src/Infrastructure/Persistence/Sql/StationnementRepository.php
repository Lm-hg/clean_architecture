<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\Stationnement;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\ValueObjects\Price\Price;

class StationnementRepository implements StationnementRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Stationnement
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM stationnements 
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM stationnements 
            ORDER BY entry_time DESC
        ');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM stationnements 
            WHERE user_id = :user_id
            ORDER BY entry_time DESC
        ');
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByParkingId(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM stationnements 
            WHERE parking_id = :parking_id
            ORDER BY entry_time DESC
        ');
        $stmt->execute(['parking_id' => $parkingId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findActiveByParkingId(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM stationnements 
            WHERE parking_id = :parking_id 
            AND status = :status
            ORDER BY entry_time DESC
        ');
        $stmt->execute([
            'parking_id' => $parkingId,
            'status' => Stationnement::STATUS_ACTIVE
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findActiveByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM stationnements 
            WHERE user_id = :user_id 
            AND status = :status
            ORDER BY entry_time DESC
        ');
        $stmt->execute([
            'user_id' => $userId,
            'status' => Stationnement::STATUS_ACTIVE
        ]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByReservationId(string $reservationId): array
    {
        // Note: La table stationnements n'a pas encore de colonne reservation_id
        // Pour l'instant, retourner un tableau vide
        // TODO: Ajouter la colonne reservation_id au schéma SQL si nécessaire
        return [];
    }

    public function findByAbonnementId(string $abonnementId): array
    {
        // Note: La table stationnements n'a pas encore de colonne abonnement_id
        // Pour l'instant, retourner un tableau vide
        // TODO: Ajouter la colonne abonnement_id au schéma SQL si nécessaire
        return [];
    }

    public function save(Stationnement $stationnement): Stationnement
    {
        if ($stationnement->getId() === null) {
            return $this->insert($stationnement);
        } else {
            return $this->update($stationnement);
        }
    }

    private function insert(Stationnement $stationnement): Stationnement
    {
        $id = $this->generateUuidV4();
        $price = $stationnement->getPrice();
        $priceAmount = $price !== null ? $price->getAmount() : null;

        $stmt = $this->pdo->prepare('
            INSERT INTO stationnements (
                id, user_id, parking_id, entry_time, exit_time,
                calculated_price, penalties, status,
                reservation_id, abonnement_id,
                created_at, updated_at
            ) VALUES (
                :id, :user_id, :parking_id, :entry_time, :exit_time,
                :calculated_price, :penalties, :status,
                :reservation_id, :abonnement_id,
                :created_at, :updated_at
            )
        ');

        $stmt->execute([
            'id' => $id,
            'user_id' => $stationnement->getUserId(),
            'parking_id' => $stationnement->getParkingId(),
            'entry_time' => $stationnement->getEntryTime()->format('Y-m-d H:i:s'),
            'exit_time' => $stationnement->getExitTime()?->format('Y-m-d H:i:s'),
            'calculated_price' => $priceAmount,
            'penalties' => $stationnement->getPenaltyAmount(),
            'status' => $stationnement->getStatus(),
            'reservation_id' => $stationnement->getReservationId(),
            'abonnement_id' => $stationnement->getAbonnementId(),
            'created_at' => $stationnement->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $stationnement->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);

        // Reconstruire avec l'ID
        return Stationnement::reconstitute(
            $id,
            $stationnement->getUserId(),
            $stationnement->getParkingId(),
            $stationnement->getEntryTime(),
            $stationnement->getExitTime(),
            $stationnement->getStatus(),
            $stationnement->getPrice(),
            $stationnement->getHasPenalty(),
            $stationnement->getPenaltyAmount(),
            $stationnement->getCreatedAt(),
            $stationnement->getUpdatedAt(),
            $stationnement->getReservationId(),
            $stationnement->getAbonnementId()
        );
    }

    private function update(Stationnement $stationnement): Stationnement
    {
        $price = $stationnement->getPrice();
        $priceAmount = $price !== null ? $price->getAmount() : null;

        $stmt = $this->pdo->prepare('
            UPDATE stationnements SET
                exit_time = :exit_time,
                calculated_price = :calculated_price,
                penalties = :penalties,
                status = :status,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $stationnement->getId(),
            'exit_time' => $stationnement->getExitTime()?->format('Y-m-d H:i:s'),
            'calculated_price' => $priceAmount,
            'penalties' => $stationnement->getPenaltyAmount(),
            'status' => $stationnement->getStatus(),
            'updated_at' => $stationnement->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);

        return $stationnement;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM stationnements WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrate(array $row): Stationnement
    {
        $price = null;
        if (isset($row['calculated_price']) && $row['calculated_price'] !== null) {
            $price = Price::fromFloat((float)$row['calculated_price'], 'EUR');
        }

        $exitTime = null;
        if (isset($row['exit_time']) && $row['exit_time'] !== null) {
            $exitTime = new \DateTime($row['exit_time']);
        }

        return Stationnement::reconstitute(
            $row['id'],
            $row['user_id'],
            $row['parking_id'],
            new \DateTime($row['entry_time']),
            $exitTime,
            $row['status'],
            $price,
            (bool)($row['penalties'] ?? 0) > 0,
            (float)($row['penalties'] ?? 0),
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at']),
            $row['reservation_id'] ?? null,
            $row['abonnement_id'] ?? null,
            $row['vehicle_plate'] ?? 'AA-000-AA'
        );
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

