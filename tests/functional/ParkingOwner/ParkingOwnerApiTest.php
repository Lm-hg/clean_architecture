<?php

namespace Tests\Functional\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Tests fonctionnels pour les API des propriétaires de parking
 * Ces tests vérifient les fonctionnalités côté propriétaire
 */
class ParkingOwnerApiTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?string $authToken = null;
    private ?string $ownerId = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        try {
            $this->pdo = require __DIR__ . '/../../../config/database.php';
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
            return;
        }
        
        // Nettoyer les tables avant chaque test
        try {
            $this->pdo->exec('TRUNCATE TABLE stationnements, reservations, parkings, users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignore if tables don't exist
        }
        
        // Créer un propriétaire pour les tests
        $this->ownerId = $this->createOwnerInDb('owner@test.com', 'password123');
    }

    protected function tearDown(): void
    {
        try {
            if ($this->pdo) {
                $this->pdo->exec('TRUNCATE TABLE stationnements, reservations, parkings, users RESTART IDENTITY CASCADE');
            }
        } catch (\PDOException $e) {
            // Ignore
        }
        parent::tearDown();
    }

    /**
     * Test: Créer un parking (Use Case propriétaire)
     */
    public function test_owner_can_create_parking(): void
    {
        // Arrange
        $this->authenticate();
        $parkingData = [
            'owner_id' => $this->ownerId,
            'title' => 'Mon Parking Test',
            'address' => '123 Rue Test',
            'city' => 'Paris',
            'postal_code' => '75001',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'price_per_hour' => 5.0,
            'total_spots' => 50,
            'available_spots' => 50,
            'opening_hours' => json_encode(['monday' => ['08:00-20:00']])
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/owner/parkings', $parkingData);

        // Assert
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertEquals('success', $response['data']['status']);
        $this->assertArrayHasKey('id', $response['data']['data']);
        $this->assertEquals('Mon Parking Test', $response['data']['data']['title']);
    }

    /**
     * Test: Voir les réservations de ses parkings (Use Case propriétaire)
     */
    public function test_owner_can_view_parking_reservations(): void
    {
        // Arrange
        $this->authenticate();
        $parkingId = $this->createParkingInDb($this->ownerId);
        $userId = $this->createUserInDb('user@test.com', 'password123');
        $this->createReservationInDb($userId, $parkingId);

        // Act
        $response = $this->makeRequest('GET', "/api/owner/parkings/{$parkingId}/reservations");

        // Assert
        $this->assertEquals(200, $response['code'], 'Response: ' . json_encode($response));
        $this->assertIsArray($response['data']['data']);
        $this->assertCount(1, $response['data']['data']);
    }

    // ==================== HELPER METHODS ====================

    private function authenticate(): void
    {
        $loginData = [
            'email' => 'owner@test.com',
            'password' => 'password123'
        ];

        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        
        if (isset($response['data']['data']['token'])) {
            $this->authToken = $response['data']['data']['token'];
        }
    }

    private function makeRequest(string $method, string $uri, array $data = [], array $headers = []): array
    {
        $baseUrl = getenv('API_BASE_URL') ?: 'http://localhost';
        $url = $baseUrl . $uri;

        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $requestHeaders = ['Content-Type: application/json'];
        if ($this->authToken) {
            $requestHeaders[] = 'Authorization: Bearer ' . $this->authToken;
        }
        foreach ($headers as $key => $value) {
            $requestHeaders[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'data' => json_decode($response, true) ?? []
        ];
    }

    private function createOwnerInDb(string $email, string $password): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) 
             VALUES (:id, 'parking_owner', 'Owner', 'Test', :email, :password, NOW(), NOW())"
        );
        $stmt->execute([
            ':id' => $id,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT)
        ]);
        return $id;
    }

    private function createUserInDb(string $email, string $password): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) 
             VALUES (:id, 'user', 'User', 'Test', :email, :password, NOW(), NOW())"
        );
        $stmt->execute([
            ':id' => $id,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT)
        ]);
        return $id;
    }

    private function createParkingInDb(string $ownerId): string
    {
        $id = $this->generateUuid();
        $stmt = $this->pdo->prepare(
            "INSERT INTO parkings (id, owner_id, title, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots, is_available, opening_hours, created_at, updated_at) 
             VALUES (:id, :owner_id, 'Test Parking', '123 Test St', 'Paris', '75001', 48.8566, 2.3522, 5.0, 50, 50, true, :opening_hours, NOW(), NOW())"
        );
        $openingHours = json_encode(['monday' => ['08:00-20:00']]);
        $stmt->execute([
            ':id' => $id,
            ':owner_id' => $ownerId,
            ':opening_hours' => $openingHours
        ]);
        return $id;
    }

    private function createReservationInDb(string $userId, string $parkingId): string
    {
        $id = $this->generateUuid();
        $startTime = (new \DateTime('+1 day 10:00'))->format('Y-m-d H:i:s');
        $endTime = (new \DateTime('+1 day 12:00'))->format('Y-m-d H:i:s');
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO reservations (id, user_id, parking_id, start_time, end_time, status, created_at, updated_at) 
             VALUES (:id, :user_id, :parking_id, :start_time, :end_time, 'confirmed', NOW(), NOW())"
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':parking_id' => $parkingId,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);
        return $id;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
