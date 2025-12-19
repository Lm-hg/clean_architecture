<?php

namespace App\Infrastructure\Persistence\Sql;

use App\Domain\Entities\SubscriptionType;
use App\Domain\Repositories\SubscriptionTypeRepositoryInterface;
use App\Domain\ValueObjects\Pricing\Price;
use App\Domain\ValueObjects\TimeSlot;
use PDO;
use MongoDB\Driver\Manager as MongoManager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Query as MongoQuery;
use MongoDB\BSON\ObjectId;

class SubscriptionTypeRepository implements SubscriptionTypeRepositoryInterface
{
    private PDO $pdo;
    private ?MongoManager $mongo;
    private string $mongoDb;
    private string $mongoCollection = 'subscription_time_slots';

    public function __construct(PDO $pdo, ?MongoManager $mongo = null, string $mongoDb = 'parking_db')
    {
        $this->pdo = $pdo;
        $this->mongo = $mongo;
        $this->mongoDb = $mongoDb;
    }

    public function save(SubscriptionType $subscriptionType): ?SubscriptionType
    {
        $timeSlots = $subscriptionType->getTimeSlots();
        $timeSlotsId = null;

        $id = $subscriptionType->getId() ?? $this->generateUuidV4();

        // Persist time slots to MongoDB if available
        if (!empty($timeSlots) && $this->mongo !== null) {
            $doc = [
                'subscription_type_id' => $id,
                'time_slots' => array_map(function ($slot) {
                    return $slot->toArray();
                }, $timeSlots),
                'created_at' => (new \DateTime())->format('c'),
                'updated_at' => (new \DateTime())->format('c'),
            ];

            try {
                $bulk = new BulkWrite();
                $insertedId = $bulk->insert($doc);
                $wc = new WriteConcern(WriteConcern::MAJORITY, 1000);
                $this->mongo->executeBulkWrite($this->mongoDb . '.' . $this->mongoCollection, $bulk, ['writeConcern' => $wc]);

                if ($insertedId !== null) {
                    $timeSlotsId = (string)$insertedId;
                }
            } catch (\Exception $e) {
                error_log("MongoDB unavailable for time slots: " . $e->getMessage());
            }
        }

        // Convert benefits array to JSON string
        $benefitsJson = json_encode($subscriptionType->getBenefits());

        // Check if exists
        $existing = $this->findById($id);

        if ($existing === null) {
            // Create new
            $stmt = $this->pdo->prepare('INSERT INTO subscription_types (id, parking_id, name, description, benefits, price, duration_days, time_slots_id, is_active, created_at, updated_at) VALUES (:id, :parking_id, :name, :description, :benefits, :price, :duration_days, :time_slots_id, :is_active, :created_at, :updated_at)');
            $success = $stmt->execute([
                ':id' => $id,
                ':parking_id' => $subscriptionType->getParkingId(),
                ':name' => $subscriptionType->getName(),
                ':description' => $subscriptionType->getDescription(),
                ':benefits' => $benefitsJson,
                ':price' => $subscriptionType->getPrice()->getAmount(),
                ':duration_days' => $subscriptionType->getDurationDays(),
                ':time_slots_id' => $timeSlotsId,
                ':is_active' => $subscriptionType->isActive() ? 1 : 0,
                ':created_at' => $subscriptionType->getCreatedAt()->format('Y-m-d H:i:s'),
                ':updated_at' => $subscriptionType->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

            if (!$success) {
                return null;
            }

            return new SubscriptionType(
                $subscriptionType->getParkingId(),
                $subscriptionType->getName(),
                $subscriptionType->getPrice(),
                $subscriptionType->getDurationDays(),
                $subscriptionType->getCreatedAt(),
                $subscriptionType->getUpdatedAt(),
                $subscriptionType->getDescription(),
                $subscriptionType->getBenefits(),
                $timeSlots,
                $subscriptionType->isActive(),
                $id
            );
        }

        // Update existing
        $stmt = $this->pdo->prepare('UPDATE subscription_types SET parking_id = :parking_id, name = :name, description = :description, benefits = :benefits, price = :price, duration_days = :duration_days, time_slots_id = :time_slots_id, is_active = :is_active, updated_at = :updated_at WHERE id = :id');
        $success = $stmt->execute([
            ':id' => $id,
            ':parking_id' => $subscriptionType->getParkingId(),
            ':name' => $subscriptionType->getName(),
            ':description' => $subscriptionType->getDescription(),
            ':benefits' => $benefitsJson,
            ':price' => $subscriptionType->getPrice()->getAmount(),
            ':duration_days' => $subscriptionType->getDurationDays(),
            ':time_slots_id' => $timeSlotsId,
            ':is_active' => $subscriptionType->isActive() ? 1 : 0,
            ':updated_at' => $subscriptionType->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            return null;
        }

        return $subscriptionType;
    }

    public function findById(string $id): ?SubscriptionType
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_types WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByParkingId(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_types WHERE parking_id = :parking_id ORDER BY created_at DESC');
        $stmt->execute([':parking_id' => $parkingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $subscriptionType = $this->hydrate($row);
            if ($subscriptionType !== null) {
                $result[] = $subscriptionType;
            }
        }

        return $result;
    }

    public function findActiveByParkingId(string $parkingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_types WHERE parking_id = :parking_id AND is_active = true ORDER BY created_at DESC');
        $stmt->execute([':parking_id' => $parkingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $subscriptionType = $this->hydrate($row);
            if ($subscriptionType !== null) {
                $result[] = $subscriptionType;
            }
        }

        return $result;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM subscription_types WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function hydrate(array $row): ?SubscriptionType
    {
        // Decode benefits JSON
        $benefits = json_decode($row['benefits'] ?? '[]', true);
        if (!is_array($benefits)) {
            $benefits = [];
        }

        // Load time slots from MongoDB if available
        $timeSlots = [];
        if ($this->mongo !== null && !empty($row['time_slots_id'])) {
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
                // Ignore MongoDB read errors
            }
        }

        $price = Price::fromFloat((float)$row['price']);

        return new SubscriptionType(
            $row['parking_id'],
            $row['name'],
            $price,
            (int)$row['duration_days'],
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at']),
            $row['description'],
            $benefits,
            $timeSlots,
            (bool)$row['is_active'],
            $row['id']
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
