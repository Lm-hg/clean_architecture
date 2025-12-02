<?php
declare(strict_types=1);

namespace Tests\Integration\Parking;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\ParkingRepository;
use App\Domain\Entities\Parking;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;
use App\Domain\ValueObjects\Pricing\Tarif;
use App\Domain\ValueObjects\Price\Price;
use App\Domain\ValueObjects\TimeSlot;

class ParkingRepositoryIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?ParkingRepository $repository = null;

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
        
        // Nettoyer les tables avant chaque test (users doit être nettoyé en dernier à cause des FK)
        try {
            $this->pdo->exec('TRUNCATE TABLE parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Si la table n'existe pas, on continue quand même
        }
        
        // Créer le repository
        $this->repository = new ParkingRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test (users doit être nettoyé en dernier à cause des FK)
        if ($this->pdo !== null) {
            try {
                $this->pdo->exec('TRUNCATE TABLE parkings, users RESTART IDENTITY CASCADE');
            } catch (\PDOException $e) {
                // Ignorer si la table n'existe pas
            }
        }
        parent::tearDown();
    }

    public function test_create_and_find_parking(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer d'abord un utilisateur (owner) pour satisfaire la contrainte FK
        $ownerId = $this->generateUuid();
        $this->createUserInDb($ownerId);
        
        $now = new \DateTime();
        
        $parking = new Parking(
            $ownerId,
            'Test Parking',
            new Address('123 Test Street', 'Paris', '75001', 'FR'),
            new GPSCoordinates(48.8566, 2.3522),
            50,
            new TarifCollection([
                new Tarif('Standard', Price::fromFloat(5.0, 'EUR'), null)
            ]),
            new OpeningHours([
                TimeSlot::fromHm(1, '08:00', '20:00'), // Monday
                TimeSlot::fromHm(2, '08:00', '20:00'), // Tuesday
            ]),
            $now,
            $now,
            'A test parking',
            null
        );
        
        // Act: Créer le parking
        $savedParking = $this->repository->save($parking);
        
        // Assert
        $this->assertNotNull($savedParking);
        $this->assertNotNull($savedParking->getId());
        
        // Act: Récupérer le parking
        $foundParking = $this->repository->findById($savedParking->getId());
        
        // Assert
        $this->assertNotNull($foundParking);
        $this->assertEquals('Test Parking', $foundParking->getTitle());
        $this->assertEquals($ownerId, $foundParking->getOwnerId());
        $this->assertEquals(50, $foundParking->getTotalSpots());
        $this->assertEquals('123 Test Street', $foundParking->getAddress()->getStreet());
        $this->assertEquals(48.8566, $foundParking->getCoordinates()->getLatitude());
        $this->assertEquals(2.3522, $foundParking->getCoordinates()->getLongitude());
    }

    public function test_find_by_location(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer d'abord un utilisateur (owner)
        $ownerId = $this->generateUuid();
        $this->createUserInDb($ownerId);
        
        // Créer plusieurs parkings à différentes positions
        $now = new \DateTime();
        
        // Parking à Paris (48.8566, 2.3522)
        $parkingParis = new Parking(
            $ownerId,
            'Paris Parking',
            new Address('1 Champs-Élysées', 'Paris', '75008', 'FR'),
            new GPSCoordinates(48.8566, 2.3522),
            30,
            new TarifCollection([new Tarif('Standard', Price::fromFloat(5.0, 'EUR'), null)]),
            new OpeningHours([TimeSlot::fromHm(1, '08:00', '20:00')]),
            $now,
            $now,
            null,
            null
        );
        
        // Parking à Lyon (45.7640, 4.8357) - plus loin
        $parkingLyon = new Parking(
            $ownerId,
            'Lyon Parking',
            new Address('1 Place Bellecour', 'Lyon', '69002', 'FR'),
            new GPSCoordinates(45.7640, 4.8357),
            20,
            new TarifCollection([new Tarif('Standard', Price::fromFloat(3.0, 'EUR'), null)]),
            new OpeningHours([TimeSlot::fromHm(1, '08:00', '20:00')]),
            $now,
            $now,
            null,
            null
        );
        
        $this->repository->save($parkingParis);
        $this->repository->save($parkingLyon);
        
        // Act: Rechercher autour de Paris avec un rayon de 10km
        $results = $this->repository->findByLocation(48.8566, 2.3522, 10.0);
        
        // Assert: Devrait trouver le parking de Paris mais pas celui de Lyon
        $this->assertGreaterThanOrEqual(1, count($results));
        $foundParis = false;
        foreach ($results as $parking) {
            if ($parking->getTitle() === 'Paris Parking') {
                $foundParis = true;
                break;
            }
        }
        $this->assertTrue($foundParis, 'Paris parking should be found');
    }

    public function test_find_by_owner_id(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer d'abord les utilisateurs (owners)
        $ownerId1 = $this->generateUuid();
        $ownerId2 = $this->generateUuid();
        $this->createUserInDb($ownerId1);
        $this->createUserInDb($ownerId2);
        $now = new \DateTime();
        
        $parking1 = $this->createTestParking($ownerId1, 'Parking 1', $now);
        $parking2 = $this->createTestParking($ownerId1, 'Parking 2', $now);
        $parking3 = $this->createTestParking($ownerId2, 'Parking 3', $now);
        
        $this->repository->save($parking1);
        $this->repository->save($parking2);
        $this->repository->save($parking3);
        
        // Act
        $results = $this->repository->findByOwnerId($ownerId1);
        
        // Assert
        $this->assertCount(2, $results);
        foreach ($results as $parking) {
            $this->assertEquals($ownerId1, $parking->getOwnerId());
        }
    }

    public function test_update_parking(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer d'abord un utilisateur (owner)
        $ownerId = $this->generateUuid();
        $this->createUserInDb($ownerId);
        $now = new \DateTime();
        
        $parking = $this->createTestParking($ownerId, 'Original Title', $now);
        $savedParking = $this->repository->save($parking);
        
        // Act: Modifier le parking
        $savedParking->updateDetails('Updated Title', 'Updated description', $savedParking->getAddress());
        $updatedParking = $this->repository->save($savedParking);
        
        // Assert
        $foundParking = $this->repository->findById($updatedParking->getId());
        $this->assertEquals('Updated Title', $foundParking->getTitle());
        $this->assertEquals('Updated description', $foundParking->getDescription());
    }

    public function test_find_available_parkings(): void
    {
        if ($this->pdo === null || $this->repository === null) {
            $this->markTestSkipped('Database not available');
            return;
        }
        
        // Arrange: Créer d'abord un utilisateur (owner)
        $ownerId = $this->generateUuid();
        $this->createUserInDb($ownerId);
        $now = new \DateTime();
        
        $availableParking = $this->createTestParking($ownerId, 'Available Parking', $now);
        $unavailableParking = $this->createTestParking($ownerId, 'Unavailable Parking', $now);
        $unavailableParking->setAvailable(false);
        
        $this->repository->save($availableParking);
        $this->repository->save($unavailableParking);
        
        // Act
        $results = $this->repository->findAvailableParkings();
        
        // Assert
        $this->assertGreaterThanOrEqual(1, count($results));
        foreach ($results as $parking) {
            $this->assertTrue($parking->getIsAvailable());
            $this->assertTrue($parking->hasAvailableSpots());
        }
    }

    private function createTestParking(string $ownerId, string $title, \DateTime $now): Parking
    {
        return new Parking(
            $ownerId,
            $title,
            new Address('123 Test St', 'Test City', '12345', 'FR'),
            new GPSCoordinates(48.8566, 2.3522),
            10,
            new TarifCollection([new Tarif('Standard', Price::fromFloat(5.0, 'EUR'), null)]),
            new OpeningHours([TimeSlot::fromHm(1, '08:00', '20:00')]),
            $now,
            $now,
            null,
            null
        );
    }

    private function createUserInDb(string $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) 
            VALUES (:id, 'ownerParking', 'Test', 'Owner', :email, :password, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $userId,
            'email' => 'owner_' . uniqid() . '@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT)
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

