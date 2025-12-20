<?php
declare(strict_types=1);

namespace Tests\Integration\Stationnement;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\StationnementRepository;
use App\Domain\Entities\Stationnement;

class StationnementRepositoryIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?StationnementRepository $repository = null;
    private ?string $userId = null;
    private ?string $parkingId = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Connexion à la base de données
        try {
            $this->pdo = require __DIR__ . '/../../../config/database.php';
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        if ($this->pdo === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Nettoyer les tables avant chaque test
        try {
            $this->pdo->exec('TRUNCATE TABLE stationnements, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Si la table n'existe pas, on continue quand même
        }
        
        // Créer un utilisateur et un parking pour les tests (dépendances FK)
        $this->userId = $this->generateUuid();
        $this->parkingId = $this->generateUuid();
        
        $this->createUserInDb($this->userId);
        $this->createParkingInDb($this->parkingId, $this->userId);
        
        // Créer le repository
        $this->repository = new StationnementRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            try {
                $this->pdo->exec('TRUNCATE TABLE stationnements, parkings, users RESTART IDENTITY CASCADE');
            } catch (\PDOException $e) {
                // Ignore if tables don't exist
            }
        }
        parent::tearDown();
    }

    public function test_create_and_find_stationnement(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange
        $now = new \DateTime();
        $stationnement = new Stationnement(
            $this->userId,
            $this->parkingId,
            $now,
            $now,
            $now
        );
        
        // Act: Créer le stationnement
        $savedStationnement = $this->repository->save($stationnement);
        
        // Assert
        $this->assertNotNull($savedStationnement);
        $this->assertNotNull($savedStationnement->getId());
        
        // Act: Récupérer le stationnement
        $foundStationnement = $this->repository->findById($savedStationnement->getId());
        
        // Assert
        $this->assertNotNull($foundStationnement);
        $this->assertEquals($this->userId, $foundStationnement->getUserId());
        $this->assertEquals($this->parkingId, $foundStationnement->getParkingId());
        $this->assertEquals(Stationnement::STATUS_ACTIVE, $foundStationnement->getStatus());
        $this->assertNull($foundStationnement->getExitTime());
    }

    public function test_find_by_user_id(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer plusieurs stationnements
        $userId2 = $this->generateUuid();
        $this->createUserInDb($userId2);
        
        $now = new \DateTime();
        $stationnement1 = new Stationnement($this->userId, $this->parkingId, $now, $now, $now);
        $stationnement2 = new Stationnement($this->userId, $this->parkingId, $now, $now, $now);
        $stationnement3 = new Stationnement($userId2, $this->parkingId, $now, $now, $now);
        
        $this->repository->save($stationnement1);
        $this->repository->save($stationnement2);
        $this->repository->save($stationnement3);
        
        // Act
        $results = $this->repository->findByUserId($this->userId);
        
        // Assert
        $this->assertCount(2, $results);
        foreach ($results as $stationnement) {
            $this->assertEquals($this->userId, $stationnement->getUserId());
        }
    }

    public function test_find_active_by_parking_id(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer des stationnements actifs et terminés
        $now = new \DateTime();
        $activeStationnement = new Stationnement($this->userId, $this->parkingId, $now, $now, $now);
        $completedStationnement = new Stationnement($this->userId, $this->parkingId, new \DateTime('-2 hours'), new \DateTime('-2 hours'), new \DateTime('-2 hours'));
        $completedStationnement->exit(new \DateTime('-1 hour'));
        
        $this->repository->save($activeStationnement);
        $this->repository->save($completedStationnement);
        
        // Act
        $results = $this->repository->findActiveByParkingId($this->parkingId);
        
        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isActive());
    }

    public function test_update_stationnement_on_exit(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange
        $entryTime = new \DateTime('-2 hours');
        $stationnement = new Stationnement(
            $this->userId,
            $this->parkingId,
            $entryTime,
            $entryTime,
            $entryTime
        );
        $savedStationnement = $this->repository->save($stationnement);
        
        // Act: Enregistrer la sortie
        $exitTime = new \DateTime();
        $savedStationnement->exit($exitTime);
        $savedStationnement->setPrice(\App\Domain\ValueObjects\Price\Price::fromFloat(10.0, 'EUR'));
        $updatedStationnement = $this->repository->save($savedStationnement);
        
        // Assert
        $foundStationnement = $this->repository->findById($updatedStationnement->getId());
        $this->assertNotNull($foundStationnement->getExitTime());
        $this->assertNotNull($foundStationnement->getPrice());
        $this->assertTrue($foundStationnement->isCompleted());
    }

    private function createUserInDb(string $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) 
            VALUES (:id, 'user', 'Test', 'User', :email, :password, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $userId,
            'email' => 'user_' . uniqid() . '@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);
    }

    private function createParkingInDb(string $parkingId, string $ownerId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO parkings (id, owner_id, title, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots, is_available, created_at, updated_at) 
            VALUES (:id, :owner_id, 'Test Parking', '123 Test St', 'Test City', '12345', 48.8566, 2.3522, 5.0, 10, 10, 'true', NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $parkingId,
            'owner_id' => $ownerId
        ]);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

