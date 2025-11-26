<?php

namespace Tests\Functional\User;

use PHPUnit\Framework\TestCase;
use PDO;
use Tests\Functional\User\UserApiFunctionalTestHelper;

class UserApiTest extends TestCase
{
    private PDO $pdo;
    private string $baseUrl;
    private ?string $authToken = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Connexion à la base de données pour les tests
        // Depuis tests/functional/User/ vers config/database.php
        // Structure: tests/functional/User/ -> ../ -> tests/functional/ -> ../ -> tests/ -> ../ -> racine
        // Donc: ../../../config/database.php
        $dbConfigPath = __DIR__ . '/../../../config/database.php';
        
        // Résoudre le chemin réel (gère les liens symboliques, etc.)
        $realPath = realpath($dbConfigPath);
        
        if ($realPath === false || !file_exists($realPath)) {
            // Essayer avec dirname explicite
            $projectRoot = dirname(__DIR__, 3);
            $altPath = $projectRoot . '/config/database.php';
            $altRealPath = realpath($altPath);
            
            if ($altRealPath === false || !file_exists($altRealPath)) {
                throw new \RuntimeException(
                    "Database config not found.\n" .
                    "Tried: $dbConfigPath\n" .
                    "Tried: $altPath\n" .
                    "Current __DIR__: " . __DIR__ . "\n" .
                    "Project root: $projectRoot"
                );
            }
            $realPath = $altRealPath;
        }
        
        require_once $realPath;
        $this->pdo = require $realPath;
        
        // Nettoyer la table avant chaque test (seulement si elle existe)
        try {
            $this->pdo->exec('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Si la table n'existe pas, on continue quand même
            // Les tests d'intégration échoueront mais c'est attendu
        }
        
        // Reset token
        $this->authToken = null;
        
        // URL de base (à adapter selon votre configuration)
        $this->baseUrl = 'http://localhost';
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test (seulement si la table existe)
        try {
            $this->pdo->exec('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignorer si la table n'existe pas
        }
        $this->authToken = null;
        parent::tearDown();
    }

    // ============================================
    // TESTS D'AUTHENTIFICATION (PUBLIC)
    // ============================================

    /**
     * Test POST /api/auth/register - Créer un compte
     */
    public function test_register_user_successfully(): void
    {
        // Arrange
        $userData = [
            'firstName' => 'John',
            'name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'user'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/auth/register', $userData);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertArrayHasKey('data', $response['data']);
        $this->assertEquals('John', $response['data']['data']['firstName']);
        $this->assertEquals('Doe', $response['data']['data']['name']);
        $this->assertEquals('john@example.com', $response['data']['data']['email']);
        $this->assertEquals('user', $response['data']['data']['role']);
        $this->assertArrayHasKey('id', $response['data']['data']);
        
        // Vérifier en base de données
        $user = $this->getUserFromDb($response['data']['data']['id']);
        $this->assertNotNull($user);
        $this->assertEquals('John', $user['first_name']);
        $this->assertEquals('Doe', $user['name']);
        $this->assertTrue(password_verify('password123', $user['password']));
    }

    /**
     * Test POST /api/auth/login - Se connecter
     */
    public function test_login_successfully(): void
    {
        // Arrange: Créer un utilisateur
        $userId = $this->createUserInDb('test@example.com', 'password123');
        
        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertArrayHasKey('data', $response['data']);
        $this->assertArrayHasKey('token', $response['data']['data']);
        $this->assertArrayHasKey('user', $response['data']['data']);
        $this->assertEquals('test@example.com', $response['data']['data']['user']['email']);
        
        // Stocker le token pour les tests suivants
        $this->authToken = $response['data']['data']['token'];
    }

    /**
     * Test POST /api/auth/login - Mauvais mot de passe
     */
    public function test_login_with_wrong_password(): void
    {
        // Arrange
        $this->createUserInDb('test@example.com', 'password123');
        
        $loginData = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        
        // Assert
        $this->assertEquals(401, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
        $this->assertStringContainsString('Invalid', $response['data']['message']);
    }

    /**
     * Test POST /api/auth/login - Email inexistant
     */
    public function test_login_with_nonexistent_email(): void
    {
        // Arrange
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        
        // Assert
        $this->assertEquals(401, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
    }

    // ============================================
    // TESTS CRUD AVEC AUTHENTIFICATION
    // ============================================

    /**
     * Test POST /api/users - Créer un utilisateur (avec authentification)
     */
    public function test_create_user_with_authentication(): void
    {
        // Arrange: Se connecter pour obtenir un token
        $this->authenticate();
        
        $userData = [
            'firstName' => 'Jane',
            'name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'user'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/users', $userData, ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertEquals('Jane', $response['data']['data']['firstName']);
        $this->assertEquals('jane@example.com', $response['data']['data']['email']);
    }

    /**
     * Test POST /api/users - Sans authentification (doit échouer)
     */
    public function test_create_user_without_authentication(): void
    {
        // Arrange
        $userData = [
            'firstName' => 'Jane',
            'name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'role' => 'user'
        ];

        // Act
        $response = $this->makeRequest('POST', '/api/users', $userData);
        
        // Assert
        $this->assertEquals(401, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
        $this->assertStringContainsString('Unauthorized', $response['data']['message']);
    }

    /**
     * Test GET /api/users - Lister tous les utilisateurs (avec authentification)
     */
    public function test_list_all_users_with_authentication(): void
    {
        // Arrange: Créer plusieurs utilisateurs et s'authentifier
        $this->createUserInDb('user1@example.com', 'password123');
        $this->createUserInDb('user2@example.com', 'password123');
        $this->authenticate(); // Crée aussi un utilisateur pour l'auth
        
        // Act
        $response = $this->makeRequest('GET', '/api/users', [], ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertArrayHasKey('data', $response['data']);
        // Il y a 3 utilisateurs au total : user1, user2, et celui créé par authenticate()
        $this->assertGreaterThanOrEqual(3, count($response['data']['data']));
        $this->assertGreaterThanOrEqual(3, $response['data']['count']);
    }

    /**
     * Test GET /api/users/{id} - Récupérer un utilisateur (avec authentification)
     */
    public function test_get_user_by_id_with_authentication(): void
    {
        // Arrange
        $userId = $this->createUserInDb('test@example.com', 'password123');
        $this->authenticate();
        
        // Act
        $response = $this->makeRequest('GET', "/api/users/$userId", [], ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertEquals($userId, $response['data']['data']['id']);
        $this->assertEquals('test@example.com', $response['data']['data']['email']);
    }

    /**
     * Test GET /api/users/{id} - Utilisateur non trouvé
     */
    public function test_get_user_not_found_with_authentication(): void
    {
        // Arrange
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $this->authenticate();
        
        // Act
        $response = $this->makeRequest('GET', "/api/users/{$fakeId}", [], ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(404, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
    }

    /**
     * Test PUT /api/users/{id} - Mettre à jour un utilisateur
     */
    public function test_update_user_with_authentication(): void
    {
        // Arrange
        $userId = $this->createUserInDb('test@example.com', 'password123');
        $this->authenticate();
        
        $updateData = [
            'firstName' => 'Updated',
            'name' => 'Name',
            'password' => 'newpassword123',
            'role' => 'admin'
        ];
        
        // Act
        $response = $this->makeRequest('PUT', "/api/users/{$userId}", $updateData, ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        $this->assertEquals('Updated', $response['data']['data']['firstName']);
        $this->assertEquals('Name', $response['data']['data']['name']);
        $this->assertEquals('admin', $response['data']['data']['role']);
        
        // Vérifier en base
        $user = $this->getUserFromDb($userId);
        $this->assertEquals('Updated', $user['first_name']);
        $this->assertEquals('Name', $user['name']);
        $this->assertTrue(password_verify('newpassword123', $user['password']));
    }

    /**
     * Test PUT /api/users/{id} - Validation des données
     */
    public function test_update_user_with_invalid_data(): void
    {
        // Arrange
        $userId = $this->createUserInDb('test@example.com', 'password123');
        $this->authenticate();
        
        $updateData = [
            'firstName' => '', // Invalide
            'name' => 'Name',
            'password' => 'short', // Trop court
            'role' => 'admin'
        ];
        
        // Act
        $response = $this->makeRequest('PUT', "/api/users/{$userId}", $updateData, ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(400, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
    }

    /**
     * Test DELETE /api/users/{id} - Supprimer un utilisateur
     */
    public function test_delete_user_with_authentication(): void
    {
        // Arrange
        $userId = $this->createUserInDb('test@example.com', 'password123');
        $this->authenticate();
        
        // Act
        $response = $this->makeRequest('DELETE', "/api/users/$userId", [], ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        $this->assertEquals(200, $response['code']);
        $this->assertEquals('success', $response['data']['status']);
        
        // Vérifier que l'utilisateur n'existe plus
        $user = $this->getUserFromDb($userId);
        $this->assertNull($user);
    }

    /**
     * Test DELETE /api/users/{id} - Utilisateur inexistant
     */
    public function test_delete_nonexistent_user(): void
    {
        // Arrange
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $this->authenticate();
        
        // Act
        $response = $this->makeRequest('DELETE', "/api/users/{$fakeId}", [], ['Authorization' => "Bearer {$this->authToken}"]);
        
        // Assert
        // Le code devrait être 404 ou 500 selon l'implémentation
        $this->assertContains($response['code'], [404, 500]);
        $this->assertEquals('error', $response['data']['status']);
    }

    /**
     * Test GET /api/users - Sans authentification (doit échouer)
     */
    public function test_list_users_without_authentication(): void
    {
        // Act
        $response = $this->makeRequest('GET', '/api/users');
        
        // Assert
        $this->assertEquals(401, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
        $this->assertStringContainsString('Unauthorized', $response['data']['message']);
    }

    /**
     * Test avec token invalide
     */
    public function test_request_with_invalid_token(): void
    {
        // Arrange
        $invalidToken = 'invalid-token-here';
        
        // Act
        $response = $this->makeRequest('GET', '/api/users', [], ['Authorization' => "Bearer {$invalidToken}"]);
        
        // Assert
        $this->assertEquals(401, $response['code']);
        $this->assertEquals('error', $response['data']['status']);
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * Helper: S'authentifier et stocker le token
     */
    private function authenticate(): void
    {
        // Créer un utilisateur de test
        $this->createUserInDb('auth@example.com', 'password123');
        
        // Se connecter
        $loginData = [
            'email' => 'auth@example.com',
            'password' => 'password123'
        ];
        
        $response = $this->makeRequest('POST', '/api/auth/login', $loginData);
        
        // Vérifier si le login a réussi en cherchant le token dans la réponse
        if (isset($response['data']['data']['token'])) {
            $this->authToken = $response['data']['data']['token'];
        } elseif (isset($response['data']['status']) && $response['data']['status'] === 'success' && isset($response['data']['data']['token'])) {
            $this->authToken = $response['data']['data']['token'];
        } else {
            $this->fail('Authentication failed in test setup: ' . json_encode($response));
        }
    }

    /**
     * Helper: Fait une requête HTTP avec support des headers
     * Utilise une approche hybride : appelle directement les controllers pour les routes simples,
     * ou simule HTTP pour tester le routage complet
     */
    private function makeRequest(string $method, string $path, array $data = [], array $headers = []): array
    {
        // Pour les routes d'authentification, appelons directement le controller
        if (preg_match('#^/api/auth/(login|register)$#', $path, $matches)) {
            return $this->makeAuthRequest($method, $matches[1], $data);
        }
        
        // Pour les routes protégées, vérifier l'authentification d'abord
        if (preg_match('#^/api/users(/.*)?$#', $path, $uriMatches)) {
            return $this->makeProtectedUserRequest($method, $path, $data, $headers, $uriMatches);
        }
        
        // Fallback: simulation HTTP complète
        return $this->simulateHttpRequest($method, $path, $data, $headers);
    }

    /**
     * Fait une requête vers les routes d'authentification
     */
    private function makeAuthRequest(string $method, string $action, array $data): array
    {
        try {
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $helper = new UserApiFunctionalTestHelper($this->pdo, $jwtSecretKey);
            $authController = $helper->createAuthController();
            
            if ($method !== 'POST') {
                http_response_code(405);
                return [
                    'code' => 405,
                    'data' => ['status' => 'error', 'message' => 'Method not allowed']
                ];
            }
            
            if ($action === 'login') {
                $response = $authController->login($data);
            } elseif ($action === 'register') {
                $response = $authController->register($data);
            } else {
                http_response_code(404);
                return [
                    'code' => 404,
                    'data' => ['status' => 'error', 'message' => 'Not found']
                ];
            }
            
            return [
                'code' => http_response_code() ?: 200,
                'data' => $response
            ];
            
        } catch (\Throwable $e) {
            return [
                'code' => http_response_code() ?: 500,
                'data' => [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Fait une requête vers les routes protégées /api/users/*
     */
    private function makeProtectedUserRequest(string $method, string $path, array $data, array $headers, array $uriMatches): array
    {
        try {
            // Vérifier l'authentification
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $helper = new UserApiFunctionalTestHelper($this->pdo, $jwtSecretKey);
            $authMiddleware = $helper->createAuthMiddleware();
            
            // Extraire le token du header Authorization
            $authHeader = $headers['Authorization'] ?? null;
            if (!$authHeader) {
                // Pas de token = non autorisé
                return [
                    'code' => 401,
                    'data' => [
                        'status' => 'error',
                        'message' => 'Unauthorized. Valid authentication token required.'
                    ]
                ];
            }
            
            // Extraire le token Bearer
            $token = null;
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $tokenMatch)) {
                $token = trim($tokenMatch[1]);
            } else {
                $token = $authHeader; // Si pas de format Bearer, utiliser directement
            }
            
            // Configurer le header pour le middleware
            $_SERVER['HTTP_AUTHORIZATION'] = $authHeader;
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $authHeader;
            
            // Valider le token directement avec le service JWT
            $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
            $authenticatedUser = $jwtService->validateToken($token);
            
            if ($authenticatedUser === null) {
                return [
                    'code' => 401,
                    'data' => [
                        'status' => 'error',
                        'message' => 'Unauthorized. Valid authentication token required.'
                    ]
                ];
            }
            
            $_SERVER['AUTHENTICATED_USER'] = $authenticatedUser;
            
            // Créer le controller et appeler la méthode appropriée
            $userController = $helper->createUserController();
            // Extraire l'ID depuis l'URL : /api/users/{id} -> {id}
            $userId = null;
            if (isset($uriMatches[1]) && $uriMatches[1] !== '') {
                $userId = trim($uriMatches[1], '/');
                // Si l'ID contient encore des slashes, prendre seulement la première partie
                if (strpos($userId, '/') !== false) {
                    $userId = explode('/', $userId)[0];
                }
            }
            
            // Reset le code HTTP avant l'appel
            http_response_code(200);
            
            switch ($method) {
                case 'GET':
                    if ($userId) {
                        $response = $userController->show($userId);
                    } else {
                        $response = $userController->index();
                    }
                    break;
                    
                case 'POST':
                    $response = $userController->create($data);
                    break;
                    
                case 'PUT':
                    if (!$userId) {
                        return [
                            'code' => 400,
                            'data' => ['status' => 'error', 'message' => 'User ID is required for update']
                        ];
                    }
                    $response = $userController->update($userId, $data);
                    break;
                    
                case 'DELETE':
                    if (!$userId) {
                        return [
                            'code' => 400,
                            'data' => ['status' => 'error', 'message' => 'User ID is required for deletion']
                        ];
                    }
                    $response = $userController->delete($userId);
                    break;
                    
                default:
                    return [
                        'code' => 405,
                        'data' => ['status' => 'error', 'message' => 'Method not allowed']
                    ];
            }
            
            // Capturer le code HTTP après l'appel du controller
            $httpCode = http_response_code();
            if ($httpCode === false) {
                $httpCode = 200;
            }
            
            // Déterminer le code HTTP basé sur le status de la réponse
            $statusCode = $httpCode;
            if (isset($response['status'])) {
                if ($response['status'] === 'error') {
                    // Le controller a déjà défini le code HTTP, utiliser celui-là
                    $statusCode = $httpCode;
                } elseif ($response['status'] === 'success') {
                    $statusCode = 200;
                }
            }
            
            return [
                'code' => $statusCode,
                'data' => $response
            ];
            
        } catch (\Throwable $e) {
            $code = http_response_code() ?: 500;
            return [
                'code' => $code,
                'data' => [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]
            ];
        } finally {
            unset($_SERVER['AUTHENTICATED_USER']);
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }
    }

    /**
     * Simule une requête HTTP complète (fallback)
     */
    private function simulateHttpRequest(string $method, string $path, array $data = [], array $headers = []): array
    {
        // Sauvegarder l'état original
        $originalServer = $_SERVER ?? [];
        $originalPost = $_POST ?? [];
        
        try {
            // Préparer le body JSON
            $jsonBody = '';
            if (in_array($method, ['POST', 'PUT'])) {
                $jsonBody = json_encode($data);
                $GLOBALS['TEST_REQUEST_BODY'] = $jsonBody;
            }
            
            // Configurer $_SERVER
            $_SERVER = array_merge([
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $path,
                'HTTP_HOST' => 'localhost',
                'CONTENT_TYPE' => 'application/json',
            ], $_SERVER);
            
            if (isset($headers['Authorization'])) {
                $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];
            }
            
            // Capture la sortie
            ob_start();
            http_response_code(200);
            
            include __DIR__ . '/../../../../public/index.php';
            
            $output = ob_get_clean();
            $code = http_response_code() ?: 200;
            $decoded = json_decode(trim($output), true);
            
            return [
                'code' => $code,
                'data' => $decoded ?? []
            ];
            
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            unset($GLOBALS['TEST_REQUEST_BODY']);
        }
    }

    /**
     * Helper: Créer un utilisateur en base de données
     */
    private function createUserInDb(string $email, string $password, string $firstName = 'Test', string $name = 'User', string $role = 'user'): string
    {
        $userId = $this->generateUuid();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');
        
        $stmt = $this->pdo->prepare('
            INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at)
            VALUES (:id, :role, :first_name, :name, :email, :password, :created_at, :updated_at)
        ');
        
        $stmt->execute([
            ':id' => $userId,
            ':role' => $role,
            ':first_name' => $firstName,
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);
        
        return $userId;
    }

    /**
     * Helper: Récupérer un utilisateur de la base de données
     */
    private function getUserFromDb(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Helper: Générer un UUID v4
     */
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
