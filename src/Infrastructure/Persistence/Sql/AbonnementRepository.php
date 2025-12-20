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
    private ?MongoManager $mongo;
    private string $mongoDb;
    private string $mongoCollection = 'creneaux_abonnements';

    public function __construct(PDO $pdo, ?MongoManager $mongo = null, string $mongoDb = 'parking_db')
    {
        $this->pdo = $pdo;
        $this->mongo = $mongo;
        $this->mongoDb = $mongoDb;
    }

    public function save(Abonnement $abonnement): ?Abonnement
    {
        // Préparer les identifiants et persister les créneaux horaires dans MongoDB (si disponible)
        $timeSlots = $abonnement->getTimeSlots();
        $timeSlotsId = null;

        // S'assurer d'utiliser un seul UUID pour la ligne SQL et le document Mongo lors de la création
        $id = $abonnement->getId() ?? $this->generateUuidV4();

        if (!empty($timeSlots) && $this->mongo !== null) {
            // Utiliser MongoDB uniquement s'il est disponible
            $doc = [
                'abonnement_id' => $id,
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
                $result = $this->mongo->executeBulkWrite($this->mongoDb . '.' . $this->mongoCollection, $bulk, ['writeConcern' => $wc]);

                if ($insertedId !== null) {
                    $timeSlotsId = (string)$insertedId;
                }
            } catch (\Exception $e) {
                // MongoDB non disponible - continuer sans créneaux horaires
                error_log("MongoDB unavailable for time slots: " . $e->getMessage());
            }
        }

        // Vérifier si l'entité existe
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

            // Retourner l'entité sauvegardée avec son ID
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

        // Chemin de mise à jour : mettre à jour la ligne abonnements et remplacer le document créneaux horaires si nécessaire
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

        // S'il y avait un time_slots_id existant et que nous avons de nouveaux créneaux, supprimer l'ancien document Mongo
        try {
            $oldStmt = $this->pdo->prepare('SELECT time_slots_id FROM abonnements WHERE id = :id');
            $oldStmt->execute([':id' => $id]);
            $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
            $oldTimeSlotsId = $oldRow['time_slots_id'] ?? null;

            if ($this->mongo !== null && !empty($oldTimeSlotsId) && !empty($timeSlotsId) && $oldTimeSlotsId !== $timeSlotsId) {
                try {
                    $bulkDel = new BulkWrite();
                    $bulkDel->delete(['_id' => new ObjectId($oldTimeSlotsId)], ['limit' => 1]);
                    $this->mongo->executeBulkWrite($this->mongoDb . '.' . $this->mongoCollection, $bulkDel, ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY, 1000)]);
                } catch (\Throwable $e) {
                    // Enregistrer ou ignorer l'échec de suppression ; ne pas interrompre le flux de mise à jour
                }
            }
        } catch (\Throwable $e) {
            // Ignorer les erreurs de sélection
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
                // Ignorer les erreurs de lecture Mongo et continuer avec des créneaux horaires vides
            }
        }

        $price = Price::fromFloat((float)$row['price']);
        
        // Mapper is_active vers le statut
        $status = Abonnement::STATUS_ACTIVE;
        if (isset($row['is_active']) && !$row['is_active']) {
            // Si is_active = false, c'est soit cancelled soit expired
            // On vérifie si la date de fin est passée
            $endDate = new \DateTime($row['end_date']);
            $now = new \DateTime();
            if ($endDate < $now) {
                $status = Abonnement::STATUS_EXPIRED;
            } else {
                $status = Abonnement::STATUS_CANCELLED;
            }
        } elseif (isset($row['end_date'])) {
            // Vérifier si l'abonnement est expiré
            $endDate = new \DateTime($row['end_date']);
            $now = new \DateTime();
            if ($endDate < $now) {
                $status = Abonnement::STATUS_EXPIRED;
            }
        }

        return Abonnement::reconstitute(
            $row['user_id'],
            $row['parking_id'],
            $row['subscription_type'],
            $timeSlots,
            new \DateTime($row['start_date']),
            new \DateTime($row['end_date']),
            $price,
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at']),
            $status,
            (bool)($row['is_active'] ?? true),
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
        // Mapper les types du domaine vers les valeurs enum de la base de données utilisées dans le script SQL d'initialisation
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
