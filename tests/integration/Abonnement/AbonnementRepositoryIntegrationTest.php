<?php

namespace Tests\Integration\Abonnement;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Persistence\Sql\AbonnementRepository;
use App\Domain\Entities\Abonnement;
use App\Domain\ValueObjects\Pricing\Price;
use App\Domain\ValueObjects\TimeSlot;

class AbonnementRepositoryIntegrationTest extends TestCase
{
    private \PDO $pdo;
    private $mongo;
    private AbonnementRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Load environment variables from project .env if present (useful in CI/docker)
        $envFile = realpath(__DIR__ . '/../../../.env');
        if ($envFile && is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if ($k !== '') {
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }

        // Load real connections from config
        $this->pdo = require __DIR__ . '/../../../config/database.php';
        try {
            $this->mongo = require __DIR__ . '/../../../config/mongodb.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB not available: ' . $e->getMessage());
            return;
        }

        // Ensure table exists (assume migrations ran in Docker compose setup)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS abonnements (
            id varchar(100) primary key,
            user_id varchar(100),
            parking_id varchar(100),
            subscription_type varchar(50),
            start_date date,
            end_date date,
            is_active boolean,
            time_slots_id varchar(100),
            price numeric,
            created_at timestamp,
            updated_at timestamp
        )");

        $this->repo = new AbonnementRepository($this->pdo, $this->mongo, getenv('MONGO_DB') ?: 'parking_db');
    }

    protected function tearDown(): void
    {
        // cleanup abonnements table
        try {
            $this->pdo->exec('TRUNCATE TABLE abonnements CASCADE');
        } catch (\Throwable $e) {
            // ignore
        }

        parent::tearDown();
    }

    public function test_save_persists_to_postgres_and_mongo()
    {
        // Arrange
        $start = new \DateTime('2025-01-01');
        $end = new \DateTime('2025-12-31');
        $price = Price::fromFloat(9.99);
        $slot = new TimeSlot(1, 8*60, 1, 10*60); // Monday 08:00-10:00

        // Use existing seeded user and parking IDs from the DB to satisfy FK constraints
        $uRow = $this->pdo->query('SELECT id FROM users LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $pRow = $this->pdo->query('SELECT id FROM parkings LIMIT 1')->fetch(\PDO::FETCH_ASSOC);

        // If missing, try to insert minimal seed data (safe, idempotent)
        if (empty($uRow)) {
            try {
                $this->pdo->exec("INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) SELECT 'seed-user-1','user','Seed','User','seed+ci@example.com','\\$2y\\$10\\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', now(), now() WHERE NOT EXISTS (SELECT 1 FROM users);");
            } catch (\Throwable $e) {
                // ignore seed errors and fallback to skip below
            }
            $uRow = $this->pdo->query('SELECT id FROM users LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        }

        if (empty($pRow)) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO parkings (owner_id, title, description, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots, created_at, updated_at) SELECT :owner_id, 'Seed Parking', 'Seeded by test', 'Test Address', 'TestCity', '00000', 48.8566, 2.3522, 5.00, 1, 1, now(), now() WHERE NOT EXISTS (SELECT 1 FROM parkings);");
                $stmt->execute([':owner_id' => $uRow['id'] ?? 'seed-user-1']);
            } catch (\Throwable $e) {
                // ignore seed errors and fallback to skip below
            }
            $pRow = $this->pdo->query('SELECT id FROM parkings LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        }

        // If seeding did not succeed, skip the test to avoid false failures
        if (empty($uRow) || empty($pRow)) {
            $this->markTestSkipped('Seed users or parkings not present in DB');
            return;
        }

        $ab = new Abonnement(
            $uRow['id'],
            $pRow['id'],
            Abonnement::TYPE_SPECIFIQUE,
            [$slot],
            $start,
            $end,
            $price,
            new \DateTime(),
            new \DateTime()
        );

        // Act
        $saved = $this->repo->save($ab);
        $this->assertNotNull($saved);
        $id = $saved->getId();
        $this->assertNotEmpty($id);

        // Assert Postgres row exists
        $stmt = $this->pdo->prepare('SELECT * FROM abonnements WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row, 'Abonnement row not found in Postgres');
        $this->assertEquals($uRow['id'], $row['user_id']);
        $this->assertEquals($pRow['id'], $row['parking_id']);

        // Assert Mongo doc exists (time_slots_id stored)
        $this->assertNotEmpty($row['time_slots_id'], 'time_slots_id is empty in Postgres row');

        // Query Mongo for document
        $oid = new \MongoDB\BSON\ObjectId($row['time_slots_id']);
        $filter = ['_id' => $oid];
        $query = new \MongoDB\Driver\Query($filter);
        $cursor = $this->mongo->executeQuery((getenv('MONGO_DB') ?: 'parking_db') . '.creneaux_abonnements', $query);
        $docs = $cursor->toArray();
        $this->assertNotEmpty($docs, 'Mongo document not found');

        $doc = $docs[0];
        $this->assertTrue(isset($doc->time_slots), 'time_slots missing in mongo doc');
        $this->assertIsArray((array)$doc->time_slots);
    }
}
