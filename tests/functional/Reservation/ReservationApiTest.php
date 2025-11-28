<?php

namespace Tests\Functional\Reservation;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\ReservationRepository;
use App\Application\UseCases\Reservation\CreateReservationUseCase;
use App\Application\UseCases\Reservation\GetReservationUseCase;
use App\Application\UseCases\Reservation\ListReservationsForUserUseCase;
use App\Application\UseCases\Reservation\CancelReservationUseCase;
use App\Presenter\Http\Controllers\Api\ReservationController;

class ReservationApiTest extends TestCase
{
    private PDO $pdo;
    private ?string $authToken = null;
    private string $userId;
    private string $parkingId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $dbConfigPath = __DIR__ . '/../../../config/database.php';
        $realPath = realpath($dbConfigPath);
        if ($realPath === false) {
             $realPath = __DIR__ . '/../../../config/database.php';
        }
        $this->pdo = require $realPath;
        
        try {
            $this->pdo->exec('TRUNCATE TABLE reservations, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
        }
        
        // Utilisation d'un email unique pour éviter les conflits
        $uniqueEmail = 'test_' . uniqid() . '@example.com';
        $this->userId = $this->createUserInDb($uniqueEmail, 'password123');
        $this->parkingId = $this->createParkingInDb($this->userId);
    }

    protected function tearDown(): void
    {
        try {
            $this->pdo->exec('TRUNCATE TABLE reservations, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
        }
        parent::tearDown();
    }

    public function test_create_reservation(): void
    {
        $start = (new \DateTime('+1 day 10:00'))->format('Y-m-d H:i:s');
        $end = (new \DateTime('+1 day 12:00'))->format('Y-m-d H:i:s');
        
        $data = [
            'user_id' => $this->userId,
            'parking_id' => $this->parkingId,
            'start_time' => $start,
            'end_time' => $end
        ];
        
        $response = $this->makeRequest('POST', '/api/reservations', $data);
        
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertEquals('success', $response['data']['status']);
        $this->assertArrayHasKey('id', $response['data']['data']);
        $this->assertEquals('pending', $response['data']['data']['status']);
    }

    public function test_get_reservation_by_id(): void
    {
        $start = (new \DateTime('+1 day 14:00'))->format('Y-m-d H:i:s');
        $end = (new \DateTime('+1 day 16:00'))->format('Y-m-d H:i:s');
        $reservationId = $this->createReservationInDb($this->userId, $this->parkingId, $start, $end);
        
        $response = $this->makeRequest('GET', "/api/reservations/$reservationId");
        
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertEquals($reservationId, $response['data']['data']['id']);
    }

    public function test_list_reservations_for_user(): void
    {
        $this->createReservationInDb($this->userId, $this->parkingId, (new \DateTime('+1 day 08:00'))->format('Y-m-d H:i:s'), (new \DateTime('+1 day 09:00'))->format('Y-m-d H:i:s'));
        $this->createReservationInDb($this->userId, $this->parkingId, (new \DateTime('+1 day 10:00'))->format('Y-m-d H:i:s'), (new \DateTime('+1 day 11:00'))->format('Y-m-d H:i:s'));
        
        $response = $this->makeRequest('GET', '/api/reservations', ['user_id' => $this->userId]);
        
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertCount(2, $response['data']['data']);
    }

    public function test_cancel_reservation(): void
    {
        $reservationId = $this->createReservationInDb($this->userId, $this->parkingId, (new \DateTime('+2 days 10:00'))->format('Y-m-d H:i:s'), (new \DateTime('+2 days 12:00'))->format('Y-m-d H:i:s'));
        
        $response = $this->makeRequest('DELETE', "/api/reservations/$reservationId");
        
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertEquals('success', $response['data']['status']);
        
        $stmt = $this->pdo->prepare('SELECT status FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $this->assertEquals('cancelled', $stmt->fetchColumn());
    }

    private function makeRequest(string $method, string $path, array $data = []): array
    {
        // 1. Instancier la stack manuellement
        $repository = new ReservationRepository($this->pdo);
        $controller = new ReservationController(
            new CreateReservationUseCase($repository),
            new GetReservationUseCase($repository),
            new ListReservationsForUserUseCase($repository),
            new CancelReservationUseCase($repository)
        );

        // 2. Simuler le routing
        try {
            // Reset code pour le prochain appel
            http_response_code(200);

            // GET /api/reservations/{id} ou GET /api/reservations
            if ($method === 'GET') {
                if (preg_match('#^/api/reservations/([^/]+)$#', $path, $matches)) {
                    $response = $controller->show($matches[1]);
                } else {
                    // Query params handling: extract from path or data
                    $userId = $data['user_id'] ?? null;
                    if (!$userId && strpos($path, '?') !== false) {
                        parse_str(parse_url($path, PHP_URL_QUERY), $query);
                        $userId = $query['user_id'] ?? null;
                    }
                    
                    if (!$userId) {
                        return ['code' => 400, 'data' => ['status' => 'error', 'message' => 'user_id required']];
                    }
                    $response = $controller->index($userId);
                }
            }
            // POST /api/reservations
            elseif ($method === 'POST' && $path === '/api/reservations') {
                $response = $controller->create($data);
            }
            // DELETE /api/reservations/{id}
            elseif ($method === 'DELETE' && preg_match('#^/api/reservations/([^/]+)$#', $path, $matches)) {
                $response = $controller->cancel($matches[1]);
            }
            else {
                return ['code' => 404, 'data' => ['error' => 'Not Found']];
            }

            // Capture le code HTTP (qui a pu être changé par le contrôleur via http_response_code())
            $code = http_response_code() ?: 200;
            
            // Si le contrôleur retourne une structure qui contient 'status' => 'error', on peut déduire un code d'erreur
            // Mais fions-nous d'abord à http_response_code()
            
            return [
                'code' => $code,
                'data' => $response
            ];

        } catch (\Throwable $e) {
            return [
                'code' => 500,
                'data' => ['error' => $e->getMessage()]
            ];
        }
    }

    private function createUserInDb(string $email, string $password): string
    {
        $id = $this->generateUuid();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (id, role, first_name, name, email, password) VALUES (?, 'user', 'Test', 'User', ?, ?)");
        $stmt->execute([$id, $email, $hash]);
        return $id;
    }

    private function createParkingInDb(string $ownerId): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare("INSERT INTO parkings (id, owner_id, title, address, city, postal_code, price_per_hour) VALUES (?, ?, 'Parking Test', '1 Rue Test', 'Paris', '75000', 10.0)");
        $stmt->execute([$id, $ownerId]);
        return $id;
    }

    private function createReservationInDb(string $userId, string $parkingId, string $start, string $end): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare("INSERT INTO reservations (id, user_id, parking_id, start_time, end_time, status, total_price) VALUES (?, ?, ?, ?, ?, 'pending', 20.0)");
        $stmt->execute([$id, $userId, $parkingId, $start, $end]);
        return $id;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
