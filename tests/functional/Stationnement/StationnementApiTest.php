<?php
declare(strict_types=1);

namespace Tests\Functional\Stationnement;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\StationnementRepository;
use App\Infrastructure\Persistence\Sql\ParkingRepository;
use App\Infrastructure\Persistence\Sql\UserRepository;
use App\Infrastructure\Persistence\Sql\ReservationRepository;
use App\Infrastructure\Persistence\Sql\AbonnementRepository;
use App\Application\UseCases\Stationnement\EnterParkingUseCase;
use App\Application\UseCases\Stationnement\ExitParkingUseCase;
use App\Application\UseCases\Stationnement\GetStationnementUseCase;
use App\Domain\Services\PricingService;
use App\Domain\Services\PenaltyCalculator;
use App\Presenter\Http\Controllers\Api\StationnementController;
use App\Domain\Entities\Stationnement;

class StationnementApiTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?StationnementController $controller = null;
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
        
        // S'assurer que les tables existent
        $this->ensureTablesExist();
        
        // Nettoyer les tables avant chaque test
        try {
            $this->pdo->exec('TRUNCATE TABLE stationnements, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignore if tables don't exist
        }
        
        // Créer un utilisateur et un parking pour les tests
        $this->userId = $this->generateUuid();
        $this->parkingId = $this->generateUuid();
        
        $this->createUserInDb($this->userId);
        $this->createParkingInDb($this->parkingId, $this->userId);
        
        // Initialiser le contrôleur avec toutes ses dépendances
        $stationnementRepository = new StationnementRepository($this->pdo);
        $parkingRepository = new ParkingRepository($this->pdo);
        $reservationRepository = new ReservationRepository($this->pdo);
        
        // Charger MongoDB pour AbonnementRepository
        try {
            $mongo = require __DIR__ . '/../../../config/mongodb.php';
            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
            $abonnementRepository = new AbonnementRepository($this->pdo, $mongo, $mongoDb);
        } catch (\Throwable $e) {
            // Si MongoDB n'est pas disponible, créer un mock ou skip
            $this->markTestSkipped('MongoDB not available: ' . $e->getMessage());
            return;
        }
        
        $pricingService = new PricingService();
        $penaltyCalculator = new PenaltyCalculator();
        
        $enterUseCase = new EnterParkingUseCase(
            $stationnementRepository,
            $parkingRepository,
            $reservationRepository,
            $abonnementRepository
        );
        
        $exitUseCase = new ExitParkingUseCase(
            $stationnementRepository,
            $parkingRepository,
            $reservationRepository,
            $abonnementRepository,
            $pricingService,
            $penaltyCalculator
        );
        
        $getUseCase = new GetStationnementUseCase($stationnementRepository);
        
        $this->controller = new StationnementController(
            $enterUseCase,
            $exitUseCase,
            $getUseCase,
            $stationnementRepository
        );
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            try {
                $this->pdo->exec('TRUNCATE TABLE stationnements, parkings, users RESTART IDENTITY CASCADE');
            } catch (\PDOException $e) {
                // Ignore
            }
        }
        parent::tearDown();
    }

    public function test_enter_parking_successfully(): void
    {
        if ($this->controller === null) {
            $this->markTestSkipped('Controller not initialized');
            return;
        }
        
        // Arrange
        $requestData = [
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId
        ];
        
        // Act
        $response = $this->controller->enter($requestData);
        
        // Assert
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertNotNull($response['data']['id']);
        $this->assertEquals($this->userId, $response['data']['user_id']);
        $this->assertEquals($this->parkingId, $response['data']['parking_id']);
        $this->assertEquals(Stationnement::STATUS_ACTIVE, $response['data']['status']);
        $this->assertArrayHasKey('entry_time', $response['data']);
    }

    public function test_enter_parking_with_missing_fields(): void
    {
        if ($this->controller === null) {
            $this->markTestSkipped('Controller not initialized');
            return;
        }
        
        // Arrange
        $requestData = [
            'user_id' => $this->userId
            // parking_id manquant
        ];
        
        // Act
        $response = $this->controller->enter($requestData);
        
        // Assert
        $this->assertEquals('error', $response['status']);
        $this->assertArrayHasKey('message', $response);
    }

    public function test_exit_parking_successfully(): void
    {
        if ($this->controller === null) {
            $this->markTestSkipped('Controller not initialized');
            return;
        }
        
        // Arrange: Créer d'abord un stationnement
        $enterData = [
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId
        ];
        $enterResponse = $this->controller->enter($enterData);
        $stationnementId = $enterResponse['data']['id'];
        
        // Attendre un peu pour avoir une durée
        sleep(1);
        
        // Act: Sortir du parking
        $exitData = [
            'user_id' => $this->userId
        ];
        $response = $this->controller->exit($stationnementId, $exitData);
        
        // Debug: Afficher le message d'erreur si présent
        if ($response['status'] === 'error') {
            $this->fail('Exit failed with error: ' . ($response['message'] ?? 'Unknown error'));
        }
        
        // Assert
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertNotNull($response['data']['exit_time']);
        $this->assertNotNull($response['data']['price']);
        $this->assertArrayHasKey('duration_minutes', $response['data']);
    }

    public function test_get_stationnement_by_id(): void
    {
        if ($this->controller === null) {
            $this->markTestSkipped('Controller not initialized');
            return;
        }
        
        // Arrange: Créer un stationnement
        $enterData = [
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId
        ];
        $enterResponse = $this->controller->enter($enterData);
        $stationnementId = $enterResponse['data']['id'];
        
        // Act
        $response = $this->controller->show($stationnementId, $this->userId);
        
        // Assert
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals($stationnementId, $response['data']['id']);
        $this->assertEquals($this->userId, $response['data']['user_id']);
    }

    public function test_list_stationnements_for_user(): void
    {
        if ($this->controller === null) {
            $this->markTestSkipped('Controller not initialized');
            return;
        }
        
        // Arrange: Créer un premier stationnement, le terminer, puis en créer un deuxième
        $enterResponse1 = $this->controller->enter([
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId
        ]);
        $this->assertEquals('success', $enterResponse1['status'], 'First enter should succeed');
        $stationnementId1 = $enterResponse1['data']['id'];
        
        // Attendre un peu pour avoir une durée valide
        sleep(1);
        
        // Sortir du premier stationnement
        $exitResponse = $this->controller->exit($stationnementId1, ['user_id' => $this->userId]);
        if ($exitResponse['status'] !== 'success') {
            $this->fail('First exit failed: ' . ($exitResponse['message'] ?? 'Unknown error') . ' - Response: ' . json_encode($exitResponse));
        }
        $this->assertEquals('success', $exitResponse['status'], 'First exit should succeed');
        
        // Créer un deuxième stationnement
        $enterResponse2 = $this->controller->enter([
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId
        ]);
        $this->assertEquals('success', $enterResponse2['status'], 'Second enter should succeed');
        
        // Act
        $response = $this->controller->index($this->userId);
        
        // Assert
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertGreaterThanOrEqual(2, count($response['data']));
    }

    private function ensureTablesExist(): void
    {
        $initSqlPath = __DIR__ . '/../../../src/docker/postgres/init.sql';
        if (file_exists($initSqlPath)) {
            $sql = file_get_contents($initSqlPath);
            // Exécuter seulement si la table n'existe pas
            try {
                $this->pdo->query('SELECT 1 FROM stationnements LIMIT 1');
            } catch (\PDOException $e) {
                // Table n'existe pas, exécuter le script
                $statements = explode(';', $sql);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            $this->pdo->exec($statement);
                        } catch (\PDOException $e) {
                            // Ignore errors (e.g., table already exists)
                        }
                    }
                }
            }
        }
        
        // S'assurer que l'enum session_status contient 'penalized'
        try {
            $this->pdo->exec("
                DO \$\$ 
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'penalized' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'session_status')
                    ) THEN
                        ALTER TYPE session_status ADD VALUE 'penalized';
                    END IF;
                END \$\$;
            ");
        } catch (\PDOException $e) {
            // Ignore if enum doesn't exist or value already exists
        }
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
        // Créer des opening_hours par défaut (ouvert 24/7)
        $openingHours = json_encode([
            'monday' => ['open' => '00:00', 'close' => '23:59'],
            'tuesday' => ['open' => '00:00', 'close' => '23:59'],
            'wednesday' => ['open' => '00:00', 'close' => '23:59'],
            'thursday' => ['open' => '00:00', 'close' => '23:59'],
            'friday' => ['open' => '00:00', 'close' => '23:59'],
            'saturday' => ['open' => '00:00', 'close' => '23:59'],
            'sunday' => ['open' => '00:00', 'close' => '23:59']
        ]);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO parkings (id, owner_id, title, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots, is_available, opening_hours, created_at, updated_at) 
            VALUES (:id, :owner_id, 'Test Parking', '123 Test St', 'Test City', '12345', 48.8566, 2.3522, 5.0, 10, 10, 'true', :opening_hours, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $parkingId,
            'owner_id' => $ownerId,
            'opening_hours' => $openingHours
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

