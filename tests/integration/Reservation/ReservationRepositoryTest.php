<?php

namespace Tests\Integration\Reservation;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\ReservationRepository;
use App\Domain\Entities\Reservation;

class ReservationRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ReservationRepository $repository;
    private string $userId;
    private string $parkingId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Connexion à la base de données
        $this->pdo = require __DIR__ . '/../../../config/database.php';
        
        // Nettoyer les tables
        try {
            $this->pdo->exec('TRUNCATE TABLE reservations, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignore if tables don't exist yet
        }
        
        // Créer un utilisateur et un parking pour les tests (dépendances FK)
        $this->userId = $this->generateUuid();
        $this->parkingId = $this->generateUuid();
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, role, first_name, name, email, password) 
            VALUES (:id, 'user', 'Test', 'User', 'test@example.com', 'password123')
        ");
        $stmt->execute(['id' => $this->userId]);

        $stmt = $this->pdo->prepare("
            INSERT INTO parkings (id, owner_id, title, address, city, postal_code, price_per_hour) 
            VALUES (:id, :owner_id, 'Test Parking', '123 Rue Test', 'TestCity', '12345', 10.0)
        ");
        $stmt->execute([
            'id' => $this->parkingId,
            'owner_id' => $this->userId // Le user est aussi owner pour simplifier
        ]);
        
        // Créer le repository
        $this->repository = new ReservationRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        try {
            $this->pdo->exec('TRUNCATE TABLE reservations, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignore
        }
        parent::tearDown();
    }

    public function test_save_and_find_reservation(): void
    {
        // Arrange
        $start = new \DateTime('+1 hour');
        $end = new \DateTime('+2 hours');
        
        $reservation = new Reservation(
            $this->userId,
            $this->parkingId,
            $start,
            $end,
            new \DateTime(),
            new \DateTime()
        );
        
        // Act: Save
        $savedReservation = $this->repository->save($reservation);
        
        // Assert
        $this->assertNotNull($savedReservation->getId());
        $this->assertEquals($this->userId, $savedReservation->getUserId());
        
        // Act: Find
        $foundReservation = $this->repository->findById($savedReservation->getId());
        
        // Assert
        $this->assertNotNull($foundReservation);
        $this->assertEquals($savedReservation->getId(), $foundReservation->getId());
        $this->assertEquals($start->getTimestamp(), $foundReservation->getStartTime()->getTimestamp());
    }

    public function test_find_reservations_in_interval(): void
    {
        // Arrange: Créer une réservation de 14h à 16h
        $start = new \DateTime('today 14:00');
        $end = new \DateTime('today 16:00');
        
        $reservation = new Reservation(
            $this->userId,
            $this->parkingId,
            $start,
            $end,
            new \DateTime(),
            new \DateTime()
        );
        $this->repository->save($reservation);
        
        // Act & Assert
        
        // Cas 1: Chevauchement total (13h - 17h) -> Doit trouver
        $found = $this->repository->findReservationsInInterval(
            $this->parkingId,
            new \DateTime('today 13:00'),
            new \DateTime('today 17:00')
        );
        $this->assertCount(1, $found);
        
        // Cas 2: Chevauchement partiel début (13h - 15h) -> Doit trouver
        $found = $this->repository->findReservationsInInterval(
            $this->parkingId,
            new \DateTime('today 13:00'),
            new \DateTime('today 15:00')
        );
        $this->assertCount(1, $found);
        
        // Cas 3: Pas de chevauchement (16h - 18h) -> Ne doit pas trouver
        // Note: end_time de la résa est 16h00. Si on cherche à partir de 16h00, c'est contigu mais pas chevauchant
        // La requête SQL utilise < et > strict : start < q_end AND end > q_start
        // 14:00 < 18:00 (Vrai) AND 16:00 > 16:00 (Faux) -> Pas de match. Correct.
        $found = $this->repository->findReservationsInInterval(
            $this->parkingId,
            new \DateTime('today 16:00'),
            new \DateTime('today 18:00')
        );
        $this->assertCount(0, $found);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}

