<?php

namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\Abonnement;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Pricing\Price;
use PDO;
use MongoDB\Driver\Manager as MongoManager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query as MongoQuery;
use MongoDB\BSON\ObjectId;

class AbonnementRepository implements AbonnementRepositoryInterface
{
    private PDO $pdo;
    private MongoManager $mongo;
    private string $mongoDb;
    private string $mongoCollection = 'creneaux_abonnements';

    public function __construct(PDO $pdo, MongoManager $mongo, string $mongoDb = 'parking_db')
    {
        $this->pdo = $pdo;
        $this->mongo = $mongo;
        $this->mongoDb = $mongoDb;
    }

    public function save(Abonnement $abonnement): ?Abonnement
    {
        // Prepare identifiers and persist time slots to MongoDB (if any)
        $timeSlots = $abonnement->getTimeSlots();
        $timeSlotsId = null;

        // Ensure we use a single UUID for both SQL row and Mongo document when creating
        $id = $abonnement->getId() ?? $this->generateUuidV4();

        if (!empty($timeSlots)) {
            $doc = [
                'abonnement_id' => $id,
                'time_slots' => array_map(function ($slot) {
                    return $slot->toArray();
                }, $timeSlots),
                'created_at' => (new \DateTime())->format('c'),
                'updated_at' => (new \DateTime())->format('c'),
            ];

            $bulk = new BulkWrite();
            $insertedId = $bulk->insert($doc);
            $wc = new WriteConcern(WriteConcern::MAJORITY, 1000);
            $result = $this->mongo->executeBulkWrite($this->mongoDb . '.' . $this->mongoCollection, $bulk, ['writeConcern' => $wc]);

            if ($insertedId !== null) {
                $timeSlotsId = (string)$insertedId;
            }
        }

        // Check if exists
        $existing = $this->findById($id);

        if ($existing === null) {
            $stmt = $this->pdo->prepare('INSERT INTO abonnements (id, user_id, parking_id, subscription_type, start_date, end_date, is_active, time_slots_id, price, created_at, updated_at) VALUES (:id, :user_id, :parking_id, :type, :start_date, :end_date, :is_active, :time_slots_id, :price, :created_at, :updated_at)');
            $success = $stmt->execute([
                ':id' => $id,
                ':user_id' => $abonnement->getUserId(),
                ':parking_id' => $abonnement->getParkingId(),
                ':type' => $this->mapTypeToDb($abonnement->getType()),
                ':start_date' => $abonnement->getStartDate()->format('Y-m-d'),
                ':end_date' => $abonnement->getEndDate()->format('Y-m-d'),
                ':is_active' => $abonnement->isActive() ? 1 : 0,
                ':time_slots_id' => $timeSlotsId,
                ':price' => $abonnement->getMonthlyPrice()->getAmount(),
                ':created_at' => $abonnement->getCreatedAt()->format('Y-m-d H:i:s'),
                ':updated_at' => $abonnement->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

            if (!$success) {
                return null;
            }

            // Return saved entity with id
            return new Abonnement(
                $abonnement->getUserId(),
                $abonnement->getParkingId(),
                $abonnement->getType(),
                $timeSlots,
                $abonnement->getStartDate(),
                $abonnement->getEndDate(),
                $abonnement->getMonthlyPrice(),
                $abonnement->getCreatedAt(),
                $abonnement->getUpdatedAt(),
                $id
            );
        }

        // Update path: update abonnements row and replace time slots document if needed
        $stmt = $this->pdo->prepare('UPDATE abonnements SET user_id = :user_id, parking_id = :parking_id, subscription_type = :type, start_date = :start_date, end_date = :end_date, is_active = :is_active, time_slots_id = :time_slots_id, price = :price, updated_at = :updated_at WHERE id = :id');
        $success = $stmt->execute([
            ':id' => $id,
            ':user_id' => $abonnement->getUserId(),
            ':parking_id' => $abonnement->getParkingId(),
            ':type' => $this->mapTypeToDb($abonnement->getType()),
            ':start_date' => $abonnement->getStartDate()->format('Y-m-d'),
            ':end_date' => $abonnement->getEndDate()->format('Y-m-d'),
            ':is_active' => $abonnement->isActive() ? 1 : 0,
            ':time_slots_id' => $timeSlotsId,
            ':price' => $abonnement->getMonthlyPrice()->getAmount(),
            ':updated_at' => $abonnement->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            return null;
        }

        // If there was an existing time_slots_id and we have new slots, remove the old mongo doc
        try {
            $oldStmt = $this->pdo->prepare('SELECT time_slots_id FROM abonnements WHERE id = :id');
            $oldStmt->execute([':id' => $id]);
            $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
            $oldTimeSlotsId = $oldRow['time_slots_id'] ?? null;

            if (!empty($oldTimeSlotsId) && !empty($timeSlotsId) && $oldTimeSlotsId !== $timeSlotsId) {
                try {
                    $bulkDel = new BulkWrite();
                    $bulkDel->delete(['_id' => new ObjectId($oldTimeSlotsId)], ['limit' => 1]);
                    $this->mongo->executeBulkWrite($this->mongoDb . '.' . $this->mongoCollection, $bulkDel, ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY, 1000)]);
                } catch (\Throwable $e) {
                    // Log or ignore deletion failure; do not break update flow
                }
            }
        } catch (\Throwable $e) {
            // ignore select errors
        }

        return $abonnement;
    }

    public function findById(string $id): ?Abonnement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM abonnements WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $timeSlots = [];
        if (!empty($row['time_slots_id'])) {
            try {
                $oid = new ObjectId($row['time_slots_id']);
                $query = new MongoQuery(['_id' => $oid]);
                $cursor = $this->mongo->executeQuery($this->mongoDb . '.' . $this->mongoCollection, $query);
                $doc = current($cursor->toArray());
                if ($doc && isset($doc->time_slots)) {
                    foreach ($doc->time_slots as $s) {
                        $timeSlots[] = new TimeSlot((int)$s->startDay, (int)$s->startMinute, (int)$s->endDay, (int)$s->endMinute);
                    }
                }
            } catch (\Exception $e) {
                // ignore mongo read errors and continue with empty timeSlots
            }
        }

        $price = Price::fromFloat((float)$row['price']);

        return new Abonnement(
            $row['user_id'],
            $row['parking_id'],
            $row['subscription_type'],
            $timeSlots,
            new \DateTime($row['start_date']),
            new \DateTime($row['end_date']),
            $price,
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at']),
            $row['id']
        );
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM abonnements WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $a = $this->findById($r['id']);
            if ($a !== null) {
                $result[] = $a;
            }
        }

        return $result;
    }

    public function findActiveForParking(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM abonnements WHERE parking_id = :parking_id AND is_active = true');
        $stmt->execute([':parking_id' => $parkingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $a = $this->findById($r['id']);
            if ($a !== null) {
                $result[] = $a;
            }
        }

        return $result;
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        // set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function mapTypeToDb(string $type): string
    {
        // Map domain types to the DB enum values used in SQL init script
        return match ($type) {
            Abonnement::TYPE_TOTAL => 'monthly',
            Abonnement::TYPE_WEEKEND => 'weekly',
            Abonnement::TYPE_SOIR => 'daily',
            Abonnement::TYPE_SPECIFIQUE => 'custom',
            default => 'custom',
        };
    }

    private function mapDbToType(string $dbType): string
    {
        return match ($dbType) {
            'monthly' => Abonnement::TYPE_TOTAL,
            'weekly' => Abonnement::TYPE_WEEKEND,
            'daily' => Abonnement::TYPE_SOIR,
            'custom' => Abonnement::TYPE_SPECIFIQUE,
            default => Abonnement::TYPE_SPECIFIQUE,
        };
    }
}
