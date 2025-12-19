<?php

declare(strict_types=1);

/**
 * Front Controller - Entry Point
 * Clean Architecture Parking Management System
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base path
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Load Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Load environment variables (if using vlucas/phpdotenv, otherwise manual loading)
// For now, we'll use getenv() which works with docker-compose environment variables

/**
 * Helper function to get request body (supports testing via $GLOBALS)
 */
if (!function_exists('getRequestBody')) {
    function getRequestBody(): string
    {
        // Support for testing: if TEST_REQUEST_BODY is set, use it
        if (isset($GLOBALS['TEST_REQUEST_BODY'])) {
            return $GLOBALS['TEST_REQUEST_BODY'];
        }
        
        // Otherwise, read from php://input
        return file_get_contents('php://input') ?: '';
    }
}

// CORS Headers (if needed for API)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Simple router
try {
    // Health check endpoint
    if ($requestUri === '/health' || $requestUri === '/') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'message' => 'Clean Architecture Parking API is running',
            'timestamp' => date('c'),
            'environment' => [
                'php_version' => PHP_VERSION,
                'db_host' => getenv('DB_HOST') ?: 'not configured'
            ]
        ]);
        exit;
    }

    // Database connection test endpoint
    if ($requestUri === '/db-test') {
        header('Content-Type: application/json');

        try {
            // Load database connection
            $pdo = require BASE_PATH . '/config/database.php';

            // Test query - get PostgreSQL version
            $stmt = $pdo->query('SELECT version()');
            $version = $stmt->fetchColumn();

            // Test table existence query
            $stmt = $pdo->query("
                SELECT COUNT(*) as table_count 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
            ");
            $tableCount = $stmt->fetch();

            echo json_encode([
                'status' => 'success',
                'message' => 'Database connection successful',
                'database' => [
                    'version' => $version,
                    'host' => getenv('DB_HOST'),
                    'name' => getenv('DB_NAME'),
                    'tables_count' => $tableCount['table_count']
                ]
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    // MongoDB connection test endpoint
    if ($requestUri === '/mongo-test') {
        header('Content-Type: application/json');

        try {
            // Load MongoDB connection
            $mongoClient = require BASE_PATH . '/config/mongodb.php';

            // Get database
            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';

            // List collections
            $command = new MongoDB\Driver\Command(['listCollections' => 1]);
            $cursor = $mongoClient->executeCommand($mongoDb, $command);
            $collections = $cursor->toArray();

            echo json_encode([
                'status' => 'success',
                'message' => 'MongoDB connection successful',
                'database' => [
                    'host' => getenv('MONGO_HOST'),
                    'name' => $mongoDb,
                    'collections_count' => count($collections),
                    'collections' => array_map(fn($c) => $c->name, $collections)
                ]
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'MongoDB connection failed',
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    // API Auth routes (public - no authentication required)
    if (preg_match('#^/api/auth/(login|register)$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        // Initialize dependencies
        $pdo = require BASE_PATH . '/config/database.php';
        
        // Repository
        $userRepository = new \App\Infrastructure\Persistence\Sql\UserRepository($pdo);
        
        // JWT Service
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        
        // Use Cases
        $loginUserUseCase = new \App\Application\UseCases\Auth\LoginUserUseCase($userRepository, $jwtService);
        $createUserUseCase = new \App\Application\UseCases\User\CreateUserUseCase($userRepository);
        
        // Controller
        $authController = new \App\Presenter\Http\Controllers\Api\AuthController(
            $loginUserUseCase,
            $createUserUseCase
        );
        
        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Route handling
        $action = $matches[1];
        
        if ($requestMethod === 'POST') {
            if ($action === 'login') {
                $response = $authController->login($requestData);
            } elseif ($action === 'register') {
                $response = $authController->register($requestData);
            } else {
                http_response_code(404);
                $response = [
                    'status' => 'error',
                    'message' => 'Endpoint not found'
                ];
            }
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    // API Auth protected routes (require authentication)
    if (preg_match('#^/api/auth/(me|logout)$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        $action = $matches[1];
        
        // Validate JWT token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $tokenMatches)) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No token provided'
            ]);
            exit;
        }
        
        $token = $tokenMatches[1];
        
        try {
            $pdo = require __DIR__ . '/../config/database.php';
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
            $payload = $jwtService->validateToken($token);
            
            if (!$payload) {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ]);
                exit;
            }
            
            if ($requestMethod === 'GET' && $action === 'me') {
                // Get user from database
                $userRepository = new \App\Infrastructure\Persistence\Sql\UserRepository($pdo);
                $user = $userRepository->findById($payload['user_id']);
                
                if (!$user) {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'User not found'
                    ]);
                    exit;
                }
                
                $response = [
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'id' => $user->getId(),
                            'firstName' => $user->getFirstName(),
                            'name' => $user->getName(),
                            'email' => $user->getEmail(),
                            'role' => $user->getRole()
                        ]
                    ],
                    'message' => 'User retrieved successfully'
                ];
                echo json_encode($response);
            } elseif ($requestMethod === 'POST' && $action === 'logout') {
                // Logout endpoint
                $response = [
                    'status' => 'success',
                    'message' => 'Logged out successfully'
                ];
                echo json_encode($response);
            } else {
                http_response_code(405);
                $response = [
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ];
                echo json_encode($response);
            }
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // API User routes (protected - require authentication)
    if (preg_match('#^/api/user/reservations$#', $requestUri)) {
        header('Content-Type: application/json');
        
        // Authentication
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        $authenticatedUser = $authMiddleware->authenticate();
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        
        if ($requestMethod === 'GET') {
            // Get user's reservations from database
            $pdo = require BASE_PATH . '/config/database.php';
            $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
            
            try {
                $reservations = $reservationRepository->findByUserId($authenticatedUser['user_id']);
                
                // Format response in camelCase with stationnement info
                $pdo = require BASE_PATH . '/config/database.php';
                $formattedReservations = array_map(function($res) use ($pdo) {
                    $stationnementId = null;
                    $entryTime = null;
                    $exitTime = null;
                    $parkingName = 'Parking';
                    
                    $penalty = null;
                    $overstayDuration = null;
                    
                    try {
                        // Get parking name
                        $stmt = $pdo->prepare("SELECT title FROM parkings WHERE id = :id");
                        $stmt->execute([':id' => $res->getParkingId()]);
                        $parking = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($parking) {
                            $parkingName = $parking['title'];
                        }
                        
                        // Get stationnement info
                        $stmt = $pdo->prepare("SELECT id, entry_time, exit_time, penalties FROM stationnements WHERE reservation_id = :reservation_id ORDER BY entry_time DESC LIMIT 1");
                        $stmt->execute([':reservation_id' => $res->getId()]);
                        $stationnement = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($stationnement) {
                            $stationnementId = $stationnement['id'];
                            if ($stationnement['entry_time']) {
                                $entryTime = (new \DateTime($stationnement['entry_time']))->format(\DateTime::ATOM);
                            }
                            if ($stationnement['exit_time']) {
                                $exitTime = (new \DateTime($stationnement['exit_time']))->format(\DateTime::ATOM);
                                
                                // Calculate overstay duration based on reserved duration from actual entry time
                                // Not from absolute reservation end time
                                $reservationStart = $res->getStartTime();
                                $reservationEnd = $res->getEndTime();
                                $reservedDuration = $reservationEnd->getTimestamp() - $reservationStart->getTimestamp(); // en secondes
                                
                                $actualEntry = new \DateTime($stationnement['entry_time']);
                                $actualExit = new \DateTime($stationnement['exit_time']);
                                $allowedExitTime = clone $actualEntry;
                                $allowedExitTime->modify('+' . $reservedDuration . ' seconds');
                                
                                if ($actualExit > $allowedExitTime) {
                                    $overstayDuration = ($actualExit->getTimestamp() - $allowedExitTime->getTimestamp()) / 60; // en minutes
                                }
                            }
                            if ($stationnement['penalties'] > 0) {
                                $penalty = (float)$stationnement['penalties'];
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore errors
                    }
                    
                    return [
                        'id' => $res->getId(),
                        'userId' => $res->getUserId(),
                        'parkingId' => $res->getParkingId(),
                        'parkingName' => $parkingName,
                        'startTime' => $res->getStartTime()->format(\DateTime::ATOM),
                        'endTime' => $res->getEndTime()->format(\DateTime::ATOM),
                        'status' => $res->getStatus(),
                        'totalPrice' => $res->getTotalPrice(),
                        'penalty' => $penalty,
                        'overstayDuration' => $overstayDuration,
                        'stationnementId' => $stationnementId,
                        'entryTime' => $entryTime,
                        'exitTime' => $exitTime,
                        'paymentStatus' => $res->getIsPaid() ? 'paid' : 'unpaid',
                        'createdAt' => $res->getCreatedAt()->format(\DateTime::ATOM),
                        'updatedAt' => $res->getUpdatedAt()->format(\DateTime::ATOM)
                    ];
                }, $reservations);
                
                $response = [
                    'status' => 'success',
                    'data' => $formattedReservations,
                    'message' => 'Reservations retrieved successfully'
                ];
                echo json_encode($response, JSON_PRETTY_PRINT);
            } catch (\Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve reservations: ' . $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
                echo json_encode($response);
            }
        } elseif ($requestMethod === 'POST') {
            // Create new reservation - delegate to existing ReservationController
            http_response_code(501);
            $response = [
                'status' => 'error',
                'message' => 'Use POST /api/reservations endpoint to create reservations'
            ];
            echo json_encode($response);
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    // PUT /api/user/subscriptions/{id}/cancel - Cancel a subscription (Clean Architecture)
    if (preg_match('#^/api/user/subscriptions/([^/]+)/cancel$#', $requestUri, $matches) && $requestMethod === 'PUT') {
        header('Content-Type: application/json');
        
        $subscriptionId = $matches[1];
        
        try {
            // Authentication
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
            $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
            $authenticatedUser = $authMiddleware->authenticate();
            
            if ($authenticatedUser === null) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                exit;
            }
            
            $pdo = require BASE_PATH . '/config/database.php';
            
            $mongo = null;
            try {
                $mongo = require BASE_PATH . '/config/mongodb.php';
            } catch (\Throwable $e) {
                error_log('MongoDB not available: ' . $e->getMessage());
            }

            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
            
            $abonnementRepository = new \App\Infrastructure\Persistence\Sql\AbonnementRepository($pdo, $mongo, $mongoDb);
            $cancelSubscriptionUseCase = new \App\Application\UseCases\Abonnement\CancelUserSubscriptionUseCase($abonnementRepository);
            
            $subscriptionTypeRepository = new \App\Infrastructure\Persistence\Sql\SubscriptionTypeRepository($pdo, $mongo, $mongoDb);
            $getTypesUseCase = new \App\Application\UseCases\Abonnement\GetSubscriptionTypesUseCase($subscriptionTypeRepository);
            $createSubscriptionUseCase = new \App\Application\UseCases\Abonnement\CreateUserSubscriptionUseCase(
                $abonnementRepository,
                $subscriptionTypeRepository
            );
            $getUserSubscriptionsUseCase = new \App\Application\UseCases\Abonnement\GetUserSubscriptionsUseCase($abonnementRepository);
            
            $controller = new \App\Presenter\Http\Controllers\Api\UserSubscriptionController(
                $getTypesUseCase,
                $createSubscriptionUseCase,
                $getUserSubscriptionsUseCase,
                $cancelSubscriptionUseCase
            );
            
            $response = $controller->cancelSubscription($authenticatedUser['user_id'], $subscriptionId);
            echo json_encode($response, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if (preg_match('#^/api/user/subscriptions$#', $requestUri)) {
        header('Content-Type: application/json');
        
        // Authentication
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        $authenticatedUser = $authMiddleware->authenticate();
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        
        if ($requestMethod === 'GET') {
            // Clean Architecture: Use repositories and use cases
            $pdo = require BASE_PATH . '/config/database.php';
            
            $mongo = null;
            try {
                $mongo = require BASE_PATH . '/config/mongodb.php';
            } catch (\Throwable $e) {
                error_log('MongoDB not available: ' . $e->getMessage());
            }

            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
            
            $abonnementRepository = new \App\Infrastructure\Persistence\Sql\AbonnementRepository($pdo, $mongo, $mongoDb);
            $getUserSubscriptionsUseCase = new \App\Application\UseCases\Abonnement\GetUserSubscriptionsUseCase($abonnementRepository);
            
            $subscriptionTypeRepository = new \App\Infrastructure\Persistence\Sql\SubscriptionTypeRepository($pdo, $mongo, $mongoDb);
            $getTypesUseCase = new \App\Application\UseCases\Abonnement\GetSubscriptionTypesUseCase($subscriptionTypeRepository);
            $createSubscriptionUseCase = new \App\Application\UseCases\Abonnement\CreateUserSubscriptionUseCase(
                $abonnementRepository,
                $subscriptionTypeRepository
            );
            $cancelSubscriptionUseCase = new \App\Application\UseCases\Abonnement\CancelUserSubscriptionUseCase($abonnementRepository);
            
            $controller = new \App\Presenter\Http\Controllers\Api\UserSubscriptionController(
                $getTypesUseCase,
                $createSubscriptionUseCase,
                $getUserSubscriptionsUseCase,
                $cancelSubscriptionUseCase
            );
            
            $response = $controller->getUserSubscriptions($authenticatedUser['user_id']);
            echo json_encode($response, JSON_PRETTY_PRINT);
        } elseif ($requestMethod === 'POST') {
            // Create new subscription - delegate to existing AbonnementController
            http_response_code(501);
            $response = [
                'status' => 'error',
                'message' => 'Use POST /api/abonnements endpoint to create subscriptions'
            ];
            echo json_encode($response);
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    // API User Stationnements route (protected - require authentication)
    if (preg_match('#^/api/user/stationnements$#', $requestUri)) {
        header('Content-Type: application/json');
        
        // Authentication
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        $authenticatedUser = $authMiddleware->authenticate();
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        
        if ($requestMethod === 'GET') {
            // Clean Architecture: Use repositories and use cases
            $pdo = require BASE_PATH . '/config/database.php';
            
            $mongo = null;
            try {
                $mongo = require BASE_PATH . '/config/mongodb.php';
            } catch (\Throwable $e) {
                error_log('MongoDB not available: ' . $e->getMessage());
            }

            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
            
            // Repositories
            $stationnementRepository = new \App\Infrastructure\Persistence\Sql\StationnementRepository($pdo);
            $parkingRepository = new \App\Infrastructure\Persistence\Sql\ParkingRepository($pdo, $mongo, $mongoDb);
            $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
            
            // Use Case
            $getUserStationnementsUseCase = new \App\Application\UseCases\Stationnement\GetUserStationnementsUseCase(
                $stationnementRepository,
                $parkingRepository,
                $reservationRepository
            );
            
            // Controller
            $controller = new \App\Presenter\Http\Controllers\Api\UserStationnementController($getUserStationnementsUseCase);
            
            $response = $controller->index($authenticatedUser['user_id']);
            echo json_encode($response, JSON_PRETTY_PRINT);
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    // API Owner routes (simplified - require authentication)
    if (preg_match('#^/api/owner/parkings$#', $requestUri)) {
        header('Content-Type: application/json');
        
        try {
            // Include database configuration
            $pdo = require __DIR__ . '/../config/database.php';
            
            if ($requestMethod === 'GET') {
                // Extract user_id from JWT token
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $tokenMatches)) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'No token provided']);
                    exit;
                }
                
                $token = $tokenMatches[1];
                $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
                $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
                $payload = $jwtService->validateToken($token);
                
                if (!$payload || !isset($payload['user_id'])) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
                    exit;
                }
                
                $ownerId = $payload['user_id'];
                
                // Return ONLY owner's parkings filtered by owner_id
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           (SELECT COUNT(*) FROM reservations r WHERE r.parking_id = p.id AND r.status = 'confirmed') as occupied_spaces
                    FROM parkings p 
                    WHERE p.owner_id = :owner_id
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([':owner_id' => $ownerId]);
                $parkings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format data for frontend
                $formattedParkings = array_map(function($parking) {
                    return [
                        'id' => $parking['id'],
                        'title' => $parking['title'] ?? 'Parking sans nom',
                        'description' => $parking['description'] ?? '',
                        'latitude' => (float)($parking['latitude'] ?? 0),
                        'longitude' => (float)($parking['longitude'] ?? 0),
                        'totalSpots' => (int)($parking['total_spots'] ?? 0),
                        'availableSpaces' => max(0, (int)($parking['total_spots'] ?? 0) - (int)($parking['occupied_spaces'] ?? 0)),
                        'pricePerHour' => (float)($parking['price_per_hour'] ?? 2.0),
                        'openingHours' => json_decode($parking['opening_hours'] ?? '{}', true),
                        'createdAt' => $parking['created_at'] ?? date('Y-m-d H:i:s'),
                        'updatedAt' => $parking['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }, $parkings);
                
                $response = [
                    'status' => 'success',
                    'data' => $formattedParkings,
                    'message' => 'Parkings retrieved successfully'
                ];
                echo json_encode($response);
            } elseif ($requestMethod === 'POST') {
                // Create new parking
                $input = json_decode(file_get_contents('php://input'), true);
                
                $stmt = $pdo->prepare("
                    INSERT INTO parkings (
                        id, owner_id, title, description, address, city, postal_code,
                        latitude, longitude, total_spots, price_per_hour, 
                        opening_hours, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                // Générer un UUID valide
                $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                $stmt->execute([
                    $id,
                    '9ea4d0a3-2f69-414f-96ba-6ed2adbc358c', // admin@parking.com - TODO: get from JWT
                    $input['title'] ?? 'Nouveau Parking',
                    $input['description'] ?? '',
                    $input['address']['street'] ?? '',
                    $input['address']['city'] ?? '',
                    $input['address']['postalCode'] ?? '',
                    $input['coordinates']['latitude'] ?? 0,
                    $input['coordinates']['longitude'] ?? 0,
                    $input['totalSpots'] ?? 10,
                    $input['tarifs']['hourly'] ?? 2.0,
                    json_encode($input['openingHours'] ?? [])
                ]);
                
                $response = [
                    'status' => 'success',
                    'data' => [
                        'id' => $id,
                        'title' => $input['title'] ?? 'Nouveau Parking',
                        'message' => 'Parking created successfully'
                    ],
                    'message' => 'Parking created successfully'
                ];
                echo json_encode($response);
            } else {
                http_response_code(405);
                $response = [
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ];
                echo json_encode($response);
            }
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            echo json_encode($response);
        }
        exit;
    }

    // API Owner: Update parking by ID
    if (preg_match('#^/api/owner/parkings/([^/]+)$#', $requestUri, $matches)) {
        $parkingId = $matches[1];
        header('Content-Type: application/json');
        
        if ($requestMethod === 'PUT') {
            try {
                $pdo = require __DIR__ . '/../config/database.php';
                $input = json_decode(file_get_contents('php://input'), true);
                
                // Construire la requête UPDATE dynamiquement selon les champs fournis
                $updates = [];
                $params = [':id' => $parkingId];
                
                if (isset($input['title'])) {
                    $updates[] = "title = :title";
                    $params[':title'] = $input['title'];
                }
                if (isset($input['description'])) {
                    $updates[] = "description = :description";
                    $params[':description'] = $input['description'];
                }
                if (isset($input['latitude'])) {
                    $updates[] = "latitude = :latitude";
                    $params[':latitude'] = $input['latitude'];
                }
                if (isset($input['longitude'])) {
                    $updates[] = "longitude = :longitude";
                    $params[':longitude'] = $input['longitude'];
                }
                if (isset($input['totalSpots'])) {
                    $updates[] = "total_spots = :total_spots";
                    $params[':total_spots'] = $input['totalSpots'];
                }
                if (isset($input['pricePerHour'])) {
                    $updates[] = "price_per_hour = :price_per_hour";
                    $params[':price_per_hour'] = $input['pricePerHour'];
                }
                
                if (empty($updates)) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'No fields to update'
                    ]);
                    exit;
                }
                
                $updates[] = "updated_at = NOW()";
                $sql = "UPDATE parkings SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Récupérer le parking mis à jour
                $stmt = $pdo->prepare("SELECT * FROM parkings WHERE id = :id");
                $stmt->execute([':id' => $parkingId]);
                $parking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$parking) {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Parking not found'
                    ]);
                    exit;
                }
                
                $response = [
                    'status' => 'success',
                    'data' => [
                        'id' => $parking['id'],
                        'title' => $parking['title'],
                        'description' => $parking['description'],
                        'latitude' => (float)$parking['latitude'],
                        'longitude' => (float)$parking['longitude'],
                        'totalSpots' => (int)$parking['total_spots'],
                        'pricePerHour' => (float)$parking['price_per_hour'],
                        'availableSpaces' => (int)$parking['total_spots'], // TODO: calculer les vraies places dispo
                        'openingHours' => json_decode($parking['opening_hours'] ?? '{}', true),
                        'createdAt' => $parking['created_at'],
                        'updatedAt' => $parking['updated_at']
                    ],
                    'message' => 'Parking updated successfully'
                ];
                echo json_encode($response);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update parking: ' . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
        }
        exit;
    }

    // API Owner parking specific routes
    if (preg_match('#^/api/owner/parkings/([^/]+)/reservations$#', $requestUri, $matches)) {
        $parkingId = $matches[1];
        header('Content-Type: application/json');
        
        if ($requestMethod === 'GET') {
            try {
                $pdo = require __DIR__ . '/../config/database.php';
                // Récupérer toutes les réservations pour ce parking
                $stmt = $pdo->prepare('SELECT r.*, u.email as user_email, u.name as user_name 
                    FROM reservations r 
                    LEFT JOIN users u ON r.user_id = u.id 
                    WHERE r.parking_id = :parking_id 
                    ORDER BY r.start_time DESC');
                $stmt->execute([':parking_id' => $parkingId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $reservations = array_map(function($row) {
                    return [
                        'id' => $row['id'],
                        'userId' => $row['user_id'],
                        'userName' => $row['user_name'] ?? 'Utilisateur',
                        'userEmail' => $row['user_email'] ?? '',
                        'parkingId' => $row['parking_id'],
                        'startTime' => $row['start_time'],
                        'endTime' => $row['end_time'],
                        'totalPrice' => (float)($row['total_price'] ?? 0),
                        'status' => $row['status'],
                        'isPaid' => isset($row['payment_status']) ? $row['payment_status'] === 'paid' : false,
                        'createdAt' => $row['created_at']
                    ];
                }, $rows);
                
                $response = [
                    'status' => 'success',
                    'data' => $reservations,
                    'message' => 'Parking reservations retrieved successfully'
                ];
                echo json_encode($response);
            } catch (\Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve reservations: ' . $e->getMessage()
                ];
                echo json_encode($response);
            }
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    if (preg_match('#^/api/owner/parkings/([^/]+)/stationnements$#', $requestUri, $matches)) {
        $parkingId = $matches[1];
        header('Content-Type: application/json');
        
        if ($requestMethod === 'GET') {
            try {
                $pdo = require __DIR__ . '/../config/database.php';
                // Récupérer tous les stationnements pour ce parking
                $stmt = $pdo->prepare('SELECT s.*, u.email as user_email, u.name as user_name 
                    FROM stationnements s 
                    LEFT JOIN users u ON s.user_id = u.id 
                    WHERE s.parking_id = :parking_id 
                    ORDER BY s.entry_time DESC');
                $stmt->execute([':parking_id' => $parkingId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stationnements = array_map(function($row) {
                    return [
                        'id' => $row['id'],
                        'userId' => $row['user_id'],
                        'userName' => $row['user_name'] ?? 'Utilisateur',
                        'userEmail' => $row['user_email'] ?? '',
                        'parkingId' => $row['parking_id'],
                        'reservationId' => $row['reservation_id'],
                        'abonnementId' => $row['abonnement_id'],
                        'entryTime' => $row['entry_time'],
                        'exitTime' => $row['exit_time'],
                        'totalPrice' => (float)($row['total_price'] ?? 0),
                        'penalty' => (float)($row['penalty'] ?? 0),
                        'status' => $row['status'],
                        'createdAt' => $row['created_at']
                    ];
                }, $rows);
                
                $response = [
                    'status' => 'success',
                    'data' => $stationnements,
                    'message' => 'Parking stationnements retrieved successfully'
                ];
                echo json_encode($response);
            } catch (\Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve stationnements: ' . $e->getMessage()
                ];
                echo json_encode($response);
            }
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    // API Owner: Get parking violations (conducteurs hors créneaux)
    if (preg_match('#^/api/owner/parkings/([^/]+)/violations$#', $requestUri, $matches)) {
        $parkingId = $matches[1];
        header('Content-Type: application/json');
        
        if ($requestMethod === 'GET') {
            try {
                $pdo = require __DIR__ . '/../config/database.php';
                
                // Récupérer les stationnements actifs sans réservation ou abonnement valide
                $stmt = $pdo->prepare("
                    SELECT s.*, u.first_name, u.last_name, u.email
                    FROM stationnements s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.parking_id = :parking_id
                    AND s.exit_time IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM reservations r
                        WHERE r.user_id = s.user_id
                        AND r.parking_id = s.parking_id
                        AND r.status = 'active'
                        AND s.entry_time BETWEEN r.start_time AND r.end_time
                    )
                    ORDER BY s.entry_time DESC
                ");
                $stmt->execute(['parking_id' => $parkingId]);
                $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $formattedViolations = array_map(function($v) {
                    return [
                        'id' => $v['id'],
                        'parkingId' => $v['parking_id'],
                        'userId' => $v['user_id'],
                        'userName' => $v['first_name'] . ' ' . $v['last_name'],
                        'userEmail' => $v['email'],
                        'vehiclePlate' => $v['vehicle_plate'] ?? 'N/A',
                        'entryTime' => $v['entry_time'],
                        'status' => 'violation'
                    ];
                }, $violations);
                
                $response = [
                    'status' => 'success',
                    'data' => $formattedViolations,
                    'message' => 'Violations retrieved successfully'
                ];
                echo json_encode($response, JSON_PRETTY_PRINT);
            } catch (Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Server error: ' . $e->getMessage()
                ];
                echo json_encode($response);
            }
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    if (preg_match('#^/api/owner/parkings/([^/]+)/subscription-types$#', $requestUri, $matches)) {
        $parkingId = $matches[1];
        header('Content-Type: application/json');
        
        // Initialize database connection
        $pdo = require __DIR__ . '/../config/database.php';
        
        // Initialize SubscriptionType repository
        $subscriptionTypeRepo = new \App\Infrastructure\Persistence\Sql\SubscriptionTypeRepository($pdo, null);
        
        if ($requestMethod === 'GET') {
            try {
                $types = $subscriptionTypeRepo->findActiveByParkingId($parkingId);
                
                $formattedTypes = array_map(function ($type) {
                    return [
                        'id' => $type->getId(),
                        'parkingId' => $type->getParkingId(),
                        'name' => $type->getName(),
                        'description' => $type->getDescription(),
                        'benefits' => $type->getBenefits(),
                        'price' => $type->getPrice() ? $type->getPrice()->getAmount() : 0.0,
                        'duration' => $type->getDurationDays(),
                        'timeSlots' => array_map(fn($slot) => $slot->toArray(), $type->getTimeSlots()),
                        'isActive' => $type->isActive(),
                        'createdAt' => $type->getCreatedAt()->format('c'),
                        'updatedAt' => $type->getUpdatedAt()->format('c'),
                    ];
                }, $types);
                
                $response = [
                    'status' => 'success',
                    'data' => $formattedTypes,
                    'message' => 'Subscription types retrieved successfully'
                ];
                echo json_encode($response);
            } catch (\Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to retrieve subscription types: ' . $e->getMessage()
                ];
                echo json_encode($response);
            }
        } elseif ($requestMethod === 'POST') {
            try {
                $input = getRequestBody();
                $data = json_decode($input, true);
                
                if (!$data) {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Invalid JSON data'
                    ];
                    echo json_encode($response);
                    exit;
                }
                
                // Extract and validate data
                $name = $data['name'] ?? null;
                $price = $data['price'] ?? null;
                $duration = $data['duration'] ?? 30;
                $description = $data['description'] ?? null;
                $benefits = $data['benefits'] ?? [];
                
                if (!$name || $price === null) {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'Name and price are required'
                    ];
                    echo json_encode($response);
                    exit;
                }
                
                // Create SubscriptionType entity
                $now = new \DateTime();
                $priceObj = \App\Domain\ValueObjects\Pricing\Price::fromFloat((float)$price);
                
                $subscriptionType = new \App\Domain\Entities\SubscriptionType(
                    $parkingId,
                    $name,
                    $priceObj,
                    (int)$duration,
                    $now,
                    $now,
                    $description,
                    $benefits,
                    [], // timeSlots empty for now
                    true
                );
                
                $saved = $subscriptionTypeRepo->save($subscriptionType);
                
                if (!$saved) {
                    http_response_code(500);
                    $response = [
                        'status' => 'error',
                        'message' => 'Failed to create subscription type'
                    ];
                    echo json_encode($response);
                    exit;
                }
                
                $response = [
                    'status' => 'success',
                    'data' => [
                        'id' => $saved->getId(),
                        'parkingId' => $saved->getParkingId(),
                        'name' => $saved->getName(),
                        'description' => $saved->getDescription(),
                        'benefits' => $saved->getBenefits(),
                        'price' => $saved->getPrice()->getAmount(),
                        'duration' => $saved->getDurationDays(),
                        'isActive' => $saved->isActive(),
                        'createdAt' => $saved->getCreatedAt()->format('c'),
                        'updatedAt' => $saved->getUpdatedAt()->format('c'),
                    ],
                    'message' => 'Subscription type created successfully'
                ];
                echo json_encode($response);
            } catch (\Exception $e) {
                http_response_code(500);
                $response = [
                    'status' => 'error',
                    'message' => 'Failed to create subscription type: ' . $e->getMessage()
                ];
                echo json_encode($response);
            }
        } else {
            http_response_code(405);
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed'
            ];
            echo json_encode($response);
        }
        exit;
    }

    // API ParkingOwner routes (authentication and management)
    if (preg_match('#^/api/parking-owners(/.*)?$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST', 'PUT'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Parse query parameters
        $queryParams = $_GET;
        
        // Authentication routes (public)
        if (preg_match('#^/api/parking-owners/(register|login)$#', $requestUri, $authMatches)) {
            // Initialize dependencies
            $pdo = require BASE_PATH . '/config/database.php';
            
            // Repository
            $parkingOwnerRepository = new \App\Infrastructure\Persistence\Sql\ParkingOwnerRepository($pdo);
            
            // JWT Service
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
            
            // Use Cases
            $registerUseCase = new \App\Application\UseCases\ParkingOwner\RegisterParkingOwnerUseCase($parkingOwnerRepository, $jwtService);
            $loginUseCase = new \App\Application\UseCases\ParkingOwner\LoginParkingOwnerUseCase($parkingOwnerRepository, $jwtService);
            
            // Controller
            $parkingOwnerController = new \App\Presenter\Http\Controllers\Api\ParkingOwnerController(
                $registerUseCase,
                $loginUseCase
            );
            
            $action = $authMatches[1];
            
            if ($requestMethod === 'POST') {
                if ($action === 'register') {
                    $response = $parkingOwnerController->register($requestData);
                } elseif ($action === 'login') {
                    $response = $parkingOwnerController->login($requestData);
                } else {
                    http_response_code(404);
                    $response = ['success' => false, 'error' => 'Endpoint not found', 'status' => 404];
                }
            } else {
                http_response_code(405);
                $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
            }
            
            http_response_code($response['status']);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
        
        // Protected routes - require authentication
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        $authenticatedUser = $authMiddleware->authenticate();
        
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized', 'status' => 401]);
            exit;
        }
        
        // Parking management routes
        if (preg_match('#^/api/parking-owners/([^/]+)/parkings(/.*)?$#', $requestUri, $parkingMatches)) {
            $ownerId = $parkingMatches[1];
            $parkingPath = $parkingMatches[2] ?? '';
            
            // Verify that the authenticated user is the owner
            if ($authenticatedUser->getId() !== $ownerId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access forbidden', 'status' => 403]);
                exit;
            }
            
            // Initialize dependencies
            $pdo = require BASE_PATH . '/config/database.php';
            
            $parkingRepository = new \App\Infrastructure\Persistence\Sql\ParkingRepository($pdo);
            $parkingOwnerRepository = new \App\Infrastructure\Persistence\Sql\ParkingOwnerRepository($pdo);
            $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
            
            // Use Cases
            $createParkingUseCase = new \App\Application\UseCases\ParkingOwner\CreateParkingUseCase($parkingRepository, $parkingOwnerRepository);
            $updateTarifsUseCase = new \App\Application\UseCases\ParkingOwner\UpdateParkingTarifsUseCase($parkingRepository);
            $getAvailablePlacesUseCase = new \App\Application\UseCases\ParkingOwner\GetAvailablePlacesUseCase($parkingRepository);
            $listReservationsUseCase = new \App\Application\UseCases\ParkingOwner\ListParkingReservationsUseCase($parkingRepository, $reservationRepository);
            $calculateRevenueUseCase = new \App\Application\UseCases\ParkingOwner\CalculateMonthlyRevenueUseCase($parkingRepository, $reservationRepository);
            
            // Controller
            $parkingController = new \App\Presenter\Http\Controllers\Api\ParkingManagementController(
                $createParkingUseCase,
                $updateTarifsUseCase,
                $getAvailablePlacesUseCase,
                $listReservationsUseCase,
                $calculateRevenueUseCase
            );
            
            // Route specific parking actions
            if ($parkingPath === '' || $parkingPath === '/') {
                // /api/parking-owners/{ownerId}/parkings
                if ($requestMethod === 'POST') {
                    $response = $parkingController->createParking($ownerId, $requestData);
                } else {
                    http_response_code(405);
                    $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
                }
            } elseif (preg_match('#^/([^/]+)/tarifs$#', $parkingPath, $tarifMatches)) {
                // /api/parking-owners/{ownerId}/parkings/{parkingId}/tarifs
                $parkingId = $tarifMatches[1];
                if ($requestMethod === 'PUT') {
                    $response = $parkingController->updateTarifs($ownerId, $parkingId, $requestData);
                } else {
                    http_response_code(405);
                    $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
                }
            } elseif (preg_match('#^/([^/]+)/availability$#', $parkingPath, $availMatches)) {
                // /api/parking-owners/{ownerId}/parkings/{parkingId}/availability
                $parkingId = $availMatches[1];
                if ($requestMethod === 'GET') {
                    $response = $parkingController->getAvailability($ownerId, $parkingId);
                } else {
                    http_response_code(405);
                    $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
                }
            } elseif (preg_match('#^/([^/]+)/reservations$#', $parkingPath, $resMatches)) {
                // /api/parking-owners/{ownerId}/parkings/{parkingId}/reservations
                $parkingId = $resMatches[1];
                if ($requestMethod === 'GET') {
                    $response = $parkingController->getReservations($ownerId, $parkingId, $queryParams);
                } else {
                    http_response_code(405);
                    $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
                }
            } elseif (preg_match('#^/([^/]+)/revenue$#', $parkingPath, $revMatches)) {
                // /api/parking-owners/{ownerId}/parkings/{parkingId}/revenue
                $parkingId = $revMatches[1];
                if ($requestMethod === 'GET') {
                    $response = $parkingController->getMonthlyRevenue($ownerId, $parkingId, $queryParams);
                } else {
                    http_response_code(405);
                    $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
                }
            } else {
                http_response_code(404);
                $response = ['success' => false, 'error' => 'Endpoint not found', 'status' => 404];
            }
            
            http_response_code($response['status']);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
        
        // Profile route
        if (preg_match('#^/api/parking-owners/([^/]+)/profile$#', $requestUri, $profileMatches)) {
            $ownerId = $profileMatches[1];
            
            if ($authenticatedUser->getId() !== $ownerId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access forbidden', 'status' => 403]);
                exit;
            }
            
            $parkingOwnerController = new \App\Presenter\Http\Controllers\Api\ParkingOwnerController(null, null);
            
            if ($requestMethod === 'GET') {
                $response = $parkingOwnerController->getProfile($ownerId);
            } else {
                http_response_code(405);
                $response = ['success' => false, 'error' => 'Method not allowed', 'status' => 405];
            }
            
            http_response_code($response['status']);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }
        
        // If no specific route matched
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found', 'status' => 404]);
        exit;
    }

    // API User routes (protected - authentication required)
    if (preg_match('#^/api/users(/.*)?$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        // Initialize JWT Service and Authentication Middleware
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        
        // Verify authentication
        $authenticatedUser = $authMiddleware->authenticate();
        
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized. Valid authentication token required.'
            ], JSON_PRETTY_PRINT);
            exit;
        }
        
        // Store authenticated user info for use in controllers
        $_SERVER['AUTHENTICATED_USER'] = $authenticatedUser;
        
        // Initialize dependencies
        $pdo = require BASE_PATH . '/config/database.php';
        
        // Repository
        $userRepository = new \App\Infrastructure\Persistence\Sql\UserRepository($pdo);
        
        // Use Cases
        $createUserUseCase = new \App\Application\UseCases\User\CreateUserUseCase($userRepository);
        $getUserUseCase = new \App\Application\UseCases\User\GetUserUseCase($userRepository);
        $updateUserUseCase = new \App\Application\UseCases\User\UpdateUserUseCase($userRepository);
        $deleteUserUseCase = new \App\Application\UseCases\User\DeleteUserUseCase($userRepository);
        $listUsersUseCase = new \App\Application\UseCases\User\ListUsersUseCase($userRepository);
        
        // Controller
        $userController = new \App\Presenter\Http\Controllers\Api\UserController(
            $createUserUseCase,
            $getUserUseCase,
            $updateUserUseCase,
            $deleteUserUseCase,
            $listUsersUseCase
        );
        
        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST', 'PUT'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Route handling
        $userId = null;
        if (isset($matches[1]) && $matches[1] !== '') {
            $userId = trim($matches[1], '/');
        }
        
        switch ($requestMethod) {
            case 'GET':
                if ($userId) {
                    // GET /api/users/{id}
                    $response = $userController->show($userId);
                } else {
                    // GET /api/users
                    $response = $userController->index();
                }
                break;
                
            case 'POST':
                // POST /api/users
                $response = $userController->create($requestData);
                break;
                
            case 'PUT':
                if ($userId) {
                    // PUT /api/users/{id}
                    $response = $userController->update($userId, $requestData);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'User ID is required for update'
                    ];
                }
                break;
                
            case 'DELETE':
                if ($userId) {
                    // DELETE /api/users/{id}
                    $response = $userController->delete($userId);
                } else {
                    http_response_code(400);
                    $response = [
                        'status' => 'error',
                        'message' => 'User ID is required for deletion'
                    ];
                }
                break;
                
            default:
                http_response_code(405);
                $response = [
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    // API Parkings routes (public - for users to search/view parkings)
    if (preg_match('#^/api/parkings(/.*)?$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        try {
            $pdo = require __DIR__ . '/../config/database.php';
            
            // GET /api/parkings - Get all available parkings
            // GET /api/parkings/search?latitude=X&longitude=Y&radius=Z - Search parkings
            if ($requestMethod === 'GET' && ($requestUri === '/api/parkings' || strpos($requestUri, '/api/parkings/search') === 0)) {
                // Get query parameters for search
                $latitude = $_GET['latitude'] ?? null;
                $longitude = $_GET['longitude'] ?? null;
                $radius = $_GET['radius'] ?? 10; // default 10km
                
                $stmt = $pdo->prepare("
                    SELECT id, owner_id, title, latitude, longitude, total_spots, 
                           price_per_hour, opening_hours, 
                           created_at, updated_at
                    FROM parkings 
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
                $parkings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format parkings for frontend
                $formattedParkings = array_map(function($parking) use ($pdo) {
                    // Get current available spaces (total - occupied)
                    // Count active stationnements
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as occupied
                        FROM stationnements
                        WHERE parking_id = :parking_id
                        AND exit_time IS NULL
                    ");
                    $stmt->execute(['parking_id' => $parking['id']]);
                    $occupiedByStationnements = $stmt->fetch(PDO::FETCH_ASSOC)['occupied'] ?? 0;
                    
                    // Count active reservations
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as reserved
                        FROM reservations
                        WHERE parking_id = :parking_id
                        AND status IN ('confirmed', 'active')
                        AND start_time <= NOW() + INTERVAL '1 hour'
                        AND end_time >= NOW()
                    ");
                    $stmt->execute(['parking_id' => $parking['id']]);
                    $occupiedByReservations = $stmt->fetch(PDO::FETCH_ASSOC)['reserved'] ?? 0;
                    
                    $totalOccupied = $occupiedByStationnements + $occupiedByReservations;
                    
                    return [
                        'id' => $parking['id'],
                        'ownerId' => $parking['owner_id'],
                        'title' => $parking['title'],
                        'latitude' => (float)$parking['latitude'],
                        'longitude' => (float)$parking['longitude'],
                        'totalSpots' => (int)$parking['total_spots'],
                        'availableSpaces' => max(0, (int)$parking['total_spots'] - $totalOccupied),
                        'pricePerHour' => (float)$parking['price_per_hour'],
                        'openingHours' => json_decode($parking['opening_hours'] ?? '{}', true),
                        'createdAt' => $parking['created_at'],
                        'updatedAt' => $parking['updated_at']
                    ];
                }, $parkings);
                
                // If latitude/longitude provided, filter by distance
                if ($latitude !== null && $longitude !== null) {
                    $formattedParkings = array_filter($formattedParkings, function($parking) use ($latitude, $longitude, $radius) {
                        // Simple distance calculation (not precise but good enough)
                        $latDiff = abs($parking['latitude'] - (float)$latitude);
                        $lonDiff = abs($parking['longitude'] - (float)$longitude);
                        $distance = sqrt($latDiff * $latDiff + $lonDiff * $lonDiff) * 111; // rough km conversion
                        
                        return $distance <= $radius;
                    });
                }
                
                $response = [
                    'status' => 'success',
                    'data' => array_values($formattedParkings),
                    'message' => 'Parkings retrieved successfully'
                ];
                echo json_encode($response, JSON_PRETTY_PRINT);
                exit;
            }
            
            // GET /api/parkings/{id} - Get parking details
            if ($requestMethod === 'GET' && preg_match('#^/api/parkings/([^/]+)$#', $requestUri, $m)) {
                $parkingId = $m[1];
                
                $stmt = $pdo->prepare("
                    SELECT id, owner_id, title, latitude, longitude, total_spots, 
                           price_per_hour, opening_hours, 
                           created_at, updated_at
                    FROM parkings 
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $parkingId]);
                $parking = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$parking) {
                    http_response_code(404);
                    $response = [
                        'status' => 'error',
                        'message' => 'Parking not found'
                    ];
                    echo json_encode($response);
                    exit;
                }
                
                // Get current available spaces
                // Count active stationnements (currently occupying a spot)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as occupied
                    FROM stationnements
                    WHERE parking_id = :parking_id
                    AND exit_time IS NULL
                ");
                $stmt->execute(['parking_id' => $parkingId]);
                $occupiedByStationnements = $stmt->fetch(PDO::FETCH_ASSOC)['occupied'] ?? 0;
                
                // Count active reservations (spots reserved for future use or currently active)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as reserved
                    FROM reservations
                    WHERE parking_id = :parking_id
                    AND status IN ('confirmed', 'active')
                    AND start_time <= NOW() + INTERVAL '1 hour'
                    AND end_time >= NOW()
                ");
                $stmt->execute(['parking_id' => $parkingId]);
                $occupiedByReservations = $stmt->fetch(PDO::FETCH_ASSOC)['reserved'] ?? 0;
                
                // Total occupied spots
                $totalOccupied = $occupiedByStationnements + $occupiedByReservations;
                
                $formattedParking = [
                    'id' => $parking['id'],
                    'ownerId' => $parking['owner_id'],
                    'title' => $parking['title'],
                    'latitude' => (float)$parking['latitude'],
                    'longitude' => (float)$parking['longitude'],
                    'totalSpots' => (int)$parking['total_spots'],
                    'availableSpaces' => max(0, (int)$parking['total_spots'] - $totalOccupied),
                    'pricePerHour' => (float)$parking['price_per_hour'],
                    'openingHours' => json_decode($parking['opening_hours'] ?? '{}', true),
                    'createdAt' => $parking['created_at'],
                    'updatedAt' => $parking['updated_at']
                ];
                
                $response = [
                    'status' => 'success',
                    'data' => $formattedParking,
                    'message' => 'Parking retrieved successfully'
                ];
                echo json_encode($response, JSON_PRETTY_PRINT);
                exit;
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ];
            echo json_encode($response);
            exit;
        }
    }

    // API Owner - Confirm Reservation
    if (preg_match('#^/api/owner/reservations/([^/]+)/confirm$#', $requestUri, $matches)) {
        header('Content-Type: application/json');
        
        $reservationId = $matches[1];
        
        if ($requestMethod === 'PUT') {
            try {
                // Authentification (vérifier que c'est bien un owner)
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $authMatches)) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid authorization header']);
                    exit;
                }
                
                $token = $authMatches[1];
                $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
                $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
                
                try {
                    $payload = $jwtService->validateToken($token);
                } catch (\Exception $e) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
                    exit;
                }
                
                // Vérifier que c'est un owner
                if (($payload['role'] ?? '') !== 'parking_owner') {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Only parking owners can confirm reservations']);
                    exit;
                }
                
                // Initialize dependencies
                $pdo = require BASE_PATH . '/config/database.php';
                $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
                $confirmUseCase = new \App\Application\UseCases\Reservation\ConfirmReservationUseCase($reservationRepository);
                
                // Exécuter
                $result = $confirmUseCase->execute($reservationId);
                
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'data' => $result,
                    'message' => 'Reservation confirmed successfully'
                ]);
            } catch (\DomainException $e) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to confirm reservation: ' . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        exit;
    }

    // API Reservation routes
    if (preg_match('#^/api/reservations(/.*)?$#', $requestUri, $matches)) {
        header('Content-Type: application/json');

        // Initialize dependencies
        $pdo = require BASE_PATH . '/config/database.php';
        
        // Repositories
        $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
        $parkingRepository = new \App\Infrastructure\Persistence\Sql\ParkingRepository($pdo);
        
        // Use Cases
        $createReservationUseCase = new \App\Application\UseCases\Reservation\CreateReservationUseCase($reservationRepository, $parkingRepository);
        $getReservationUseCase = new \App\Application\UseCases\Reservation\GetReservationUseCase($reservationRepository);
        $listReservationsUseCase = new \App\Application\UseCases\Reservation\ListReservationsForUserUseCase($reservationRepository);
        $cancelReservationUseCase = new \App\Application\UseCases\Reservation\CancelReservationUseCase($reservationRepository);
        
        // Controller
        $reservationController = new \App\Presenter\Http\Controllers\Api\ReservationController(
            $createReservationUseCase,
            $getReservationUseCase,
            $listReservationsUseCase,
            $cancelReservationUseCase
        );
        
        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST', 'PUT'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }
        
        // Authenticate user for POST requests (creating reservations)
        if ($requestMethod === 'POST') {
            // Extract JWT token from Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $tokenMatches)) {
                http_response_code(401);
                $response = [
                    'status' => 'error',
                    'message' => 'Authentication required to create a reservation'
                ];
                echo json_encode($response);
                exit;
            }
            
            $token = $tokenMatches[1];
            $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
            $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
            $payload = $jwtService->validateToken($token);
            
            if (!$payload) {
                http_response_code(401);
                $response = [
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ];
                echo json_encode($response);
                exit;
            }
            
            // Convert camelCase to snake_case for backend compatibility
            $convertedData = [];
            if (isset($requestData['parkingId'])) {
                $convertedData['parking_id'] = $requestData['parkingId'];
            }
            if (isset($requestData['startTime'])) {
                $convertedData['start_time'] = $requestData['startTime'];
            }
            if (isset($requestData['endTime'])) {
                $convertedData['end_time'] = $requestData['endTime'];
            }
            
            // Inject authenticated user_id into request data
            $convertedData['user_id'] = $payload['user_id'];
            
            // Debug: Log received data
            error_log("Received request data: " . json_encode($requestData));
            error_log("Converted data: " . json_encode($convertedData));
            
            $requestData = $convertedData;
        }
        
        // ID extraction
        $resourceId = null;
        if (isset($matches[1]) && $matches[1] !== '') {
            $resourceId = trim($matches[1], '/');
        }

        // Route handling
        error_log("=== RESERVATION ROUTING ===");
        error_log("Request Method: " . $requestMethod);
        error_log("Request URI: " . $requestUri);
        error_log("Request Data keys: " . json_encode(array_keys($requestData)));
        
        switch ($requestMethod) {
            case 'POST':
                error_log("Entering POST case");
                // POST /api/reservations
                $response = $reservationController->create($requestData);
                break;
                
            case 'GET':
                if ($resourceId) {
                    // Check for /api/reservations/{id}/invoice
                    if (preg_match('#^/api/reservations/([^/]+)/invoice$#', $requestUri, $invoiceMatches)) {
                        $reservationId = $invoiceMatches[1];
                        
                        // Get reservation details
                        $stmt = $pdo->prepare("
                            SELECT r.*, p.title as parking_name, u.first_name, u.name, u.email
                            FROM reservations r
                            JOIN parkings p ON r.parking_id = p.id
                            JOIN users u ON r.user_id = u.id
                            WHERE r.id = :id
                        ");
                        $stmt->execute(['id' => $reservationId]);
                        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$reservation) {
                            http_response_code(404);
                            $response = ['status' => 'error', 'message' => 'Reservation not found'];
                        } else {
                            // Generate invoice data
                            $invoice = [
                                'id' => 'INV-' . $reservationId,
                                'reservationId' => $reservationId,
                                'parkingName' => $reservation['parking_name'],
                                'userName' => $reservation['first_name'] . ' ' . $reservation['last_name'],
                                'userEmail' => $reservation['email'],
                                'startTime' => $reservation['start_time'],
                                'endTime' => $reservation['end_time'],
                                'totalPrice' => (float)$reservation['total_price'],
                                'status' => $reservation['status'],
                                'createdAt' => $reservation['created_at'],
                                'items' => [
                                    [
                                        'description' => 'Réservation parking',
                                        'quantity' => 1,
                                        'unitPrice' => (float)$reservation['total_price'],
                                        'total' => (float)$reservation['total_price']
                                    ]
                                ]
                            ];
                            
                            $response = [
                                'status' => 'success',
                                'data' => $invoice,
                                'message' => 'Invoice generated successfully'
                            ];
                        }
                    } else {
                        // GET /api/reservations/{id}
                        $response = $reservationController->show($resourceId);
                    }
                } else {
                    // GET /api/reservations?user_id=...
                    $userId = $_GET['user_id'] ?? null;
                    if (!$userId) {
                        http_response_code(400);
                        $response = ['status' => 'error', 'message' => 'user_id query parameter required'];
                    } else {
                        $response = $reservationController->index($userId);
                    }
                }
                break;
                
            case 'DELETE':
                if ($resourceId) {
                    // DELETE /api/reservations/{id}
                    $response = $reservationController->cancel($resourceId);
                } else {
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Reservation ID required'];
                }
                break;
                
            default:
                http_response_code(405);
                $response = [
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    // API Stationnement routes
    if (preg_match('#^/api/stationnements(/.*)?$#', $requestUri, $matches)) {
        header('Content-Type: application/json');

        // Initialize dependencies
        $pdo = require BASE_PATH . '/config/database.php';
        
        // Load MongoDB for AbonnementRepository (optional - can work without it)
        $mongo = null;
        $mongoDb = 'parking_db';
        try {
            $mongo = require BASE_PATH . '/config/mongodb.php';
            $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
        } catch (\Throwable $e) {
            // MongoDB not available - will work without time slots feature
            error_log('MongoDB not available for stationnements: ' . $e->getMessage());
        }
        
        // Repositories
        $stationnementRepository = new \App\Infrastructure\Persistence\Sql\StationnementRepository($pdo);
        $parkingRepository = new \App\Infrastructure\Persistence\Sql\ParkingRepository($pdo);
        $reservationRepository = new \App\Infrastructure\Persistence\Sql\ReservationRepository($pdo);
        $abonnementRepository = new \App\Infrastructure\Persistence\Sql\AbonnementRepository($pdo, $mongo, $mongoDb);
        
        // Services
        $pricingService = new \App\Domain\Services\PricingService();
        $penaltyCalculator = new \App\Domain\Services\PenaltyCalculator();
        
        // Use Cases
        $enterParkingUseCase = new \App\Application\UseCases\Stationnement\EnterParkingUseCase(
            $stationnementRepository,
            $parkingRepository,
            $reservationRepository,
            $abonnementRepository
        );
        $exitParkingUseCase = new \App\Application\UseCases\Stationnement\ExitParkingUseCase(
            $stationnementRepository,
            $parkingRepository,
            $reservationRepository,
            $abonnementRepository,
            $pricingService,
            $penaltyCalculator
        );
        $getStationnementUseCase = new \App\Application\UseCases\Stationnement\GetStationnementUseCase($stationnementRepository);
        $startFromReservationUseCase = new \App\Application\UseCases\Stationnement\StartStationnementFromReservationUseCase(
            $stationnementRepository,
            $reservationRepository
        );
        
        // Controller
        $stationnementController = new \App\Presenter\Http\Controllers\Api\StationnementController(
            $enterParkingUseCase,
            $exitParkingUseCase,
            $getStationnementUseCase,
            $stationnementRepository
        );
        
        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST', 'PUT'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }
        
        // ID extraction
        $resourceId = null;
        $action = null;
        if (isset($matches[1]) && $matches[1] !== '') {
            $pathParts = explode('/', trim($matches[1], '/'));
            $resourceId = $pathParts[0] ?? null;
            $action = $pathParts[1] ?? null;
        }
        error_log("Stationnements route: method=$requestMethod, resourceId=$resourceId, action=$action");

        // Route handling
        switch ($requestMethod) {
            case 'POST':
                error_log("Stationnements POST: resourceId=$resourceId, action=$action");
                if ($resourceId === 'start') {
                    // POST /api/stationnements/start
                    // Authentification requise
                    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $authMatches)) {
                        http_response_code(401);
                        $response = ['status' => 'error', 'message' => 'Missing authorization header'];
                        break;
                    }
                    
                    $token = $authMatches[1];
                    $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
                    $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
                    
                    try {
                        $payload = $jwtService->validateToken($token);
                        $userId = $payload['user_id'] ?? null;
                        
                        if (!$userId) {
                            http_response_code(401);
                            $response = ['status' => 'error', 'message' => 'Invalid token payload'];
                            break;
                        }
                        
                        $reservationId = $requestData['reservationId'] ?? null;
                        if (!$reservationId) {
                            http_response_code(400);
                            $response = ['status' => 'error', 'message' => 'reservationId is required'];
                            break;
                        }
                        
                        try {
                            $result = $startFromReservationUseCase->execute($reservationId, $userId);
                            http_response_code(201);
                            $response = [
                                'status' => 'success',
                                'data' => $result,
                                'message' => 'Stationnement started successfully'
                            ];
                        } catch (\DomainException $e) {
                            error_log("StartStationnement DomainException: " . $e->getMessage());
                            http_response_code(400);
                            $response = ['status' => 'error', 'message' => $e->getMessage()];
                        } catch (\Exception $e) {
                            error_log("StartStationnement Exception: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                            http_response_code(500);
                            $response = ['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()];
                        }
                    } catch (\Exception $e) {
                        http_response_code(401);
                        $response = ['status' => 'error', 'message' => 'Invalid or expired token'];
                    }
                } elseif ($resourceId === 'enter') {
                    // POST /api/stationnements/enter
                    $response = $stationnementController->enter($requestData);
                } elseif ($resourceId && $action === 'exit') {
                    // POST /api/stationnements/{id}/exit - Requires authentication
                    error_log("Exit endpoint: resourceId=$resourceId, action=$action");
                    try {
                        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
                        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
                        $token = null;
                        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
                            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                                $token = $matches[1];
                            }
                        }
                        if (!$token) {
                            throw new \Exception('No token provided');
                        }
                        $payload = $jwtService->validateToken($token);
                        $userId = $payload['user_id'];
                        error_log("Exit endpoint: user authenticated, userId=$userId");
                        
                        // Inject user_id into request data
                        $requestData['user_id'] = $userId;
                        
                        $response = $stationnementController->exit($resourceId, $requestData);
                        error_log("Exit endpoint: controller returned response");
                    } catch (\Exception $e) {
                        error_log("Exit endpoint JWT exception: " . $e->getMessage());
                        http_response_code(401);
                        $response = ['status' => 'error', 'message' => 'Invalid or expired token'];
                    }
                } else {
                    error_log("Exit endpoint: Invalid route - resourceId=$resourceId, action=$action");
                    http_response_code(400);
                    $response = ['status' => 'error', 'message' => 'Invalid endpoint. Use /api/stationnements/enter or /api/stationnements/{id}/exit'];
                }
                break;
                
            case 'GET':
                if ($resourceId) {
                    // GET /api/stationnements/{id}
                    $userId = $_GET['user_id'] ?? null;
                    $response = $stationnementController->show($resourceId, $userId);
                } else {
                    // GET /api/stationnements?user_id=...
                    $userId = $_GET['user_id'] ?? null;
                    $response = $stationnementController->index($userId);
                }
                break;
                
            default:
                http_response_code(405);
                $response = [
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ];
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    // API Abonnement routes (protected)
    if (preg_match('#^/api/abonnements(/.*)?$#', $requestUri, $matches) || preg_match('#^/api/parkings/([^/]+)/abonnements$#', $requestUri, $pm)) {
        header('Content-Type: application/json');

        // Authentication
        $jwtSecretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
        $jwtService = new \App\Infrastructure\Services\JwtService($jwtSecretKey);
        $authMiddleware = new \App\Presenter\Http\Middleware\AuthenticationMiddleware($jwtService);
        $authenticatedUser = $authMiddleware->authenticate();
        if ($authenticatedUser === null) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }

        // Dependencies - Clean Architecture: Instantiate repositories and use cases
        $pdo = require BASE_PATH . '/config/database.php';
        
        // MongoDB optional
        $mongo = null;
        try {
            $mongo = require BASE_PATH . '/config/mongodb.php';
        } catch (\Throwable $e) {
            error_log('MongoDB not available for abonnements: ' . $e->getMessage());
        }

        $mongoDb = getenv('MONGO_DB') ?: 'parking_db';

        // Repositories
        $subscriptionTypeRepository = new \App\Infrastructure\Persistence\Sql\SubscriptionTypeRepository($pdo, $mongo, $mongoDb);
        $abonnementRepository = new \App\Infrastructure\Persistence\Sql\AbonnementRepository($pdo, $mongo, $mongoDb);

        // Use Cases
        $getTypesUseCase = new \App\Application\UseCases\Abonnement\GetSubscriptionTypesUseCase($subscriptionTypeRepository);
        $createSubscriptionUseCase = new \App\Application\UseCases\Abonnement\CreateUserSubscriptionUseCase(
            $abonnementRepository,
            $subscriptionTypeRepository
        );
        $getUserSubscriptionsUseCase = new \App\Application\UseCases\Abonnement\GetUserSubscriptionsUseCase($abonnementRepository);

        // Controller
        $userSubscriptionController = new \App\Presenter\Http\Controllers\Api\UserSubscriptionController(
            $getTypesUseCase,
            $createSubscriptionUseCase,
            $getUserSubscriptionsUseCase
        );

        $abonnementRepo = new \App\Infrastructure\Persistence\Sql\AbonnementRepository($pdo, $mongo, getenv('MONGO_DB') ?: 'parking_db');

        // UseCases
        $createUC = new \App\Application\UseCases\Abonnement\CreateAbonnementUseCase($abonnementRepo);
        $getUC = new \App\Application\UseCases\Abonnement\GetAbonnementUseCase($abonnementRepo);
        $listUC = new \App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase($abonnementRepo);
        $subscribeUC = new \App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase($abonnementRepo);
        $validateUC = new \App\Application\UseCases\Abonnement\ValidateAbonnementUseCase($abonnementRepo);

        $controller = new \App\Presenter\Http\Controllers\Api\AbonnementController($createUC, $getUC, $listUC, $subscribeUC, $validateUC);

        // Parse request body
        $requestData = [];
        if (in_array($requestMethod, ['POST', 'PUT'])) {
            $input = getRequestBody();
            $requestData = json_decode($input, true) ?? [];
        }

        // Routing
        // POST /api/abonnements - Create subscription for current user (Clean Architecture)
        if ($requestMethod === 'POST' && preg_match('#^/api/abonnements$#', $requestUri)) {
            $response = $userSubscriptionController->createSubscription($authenticatedUser['user_id'], $requestData);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }

        // GET /api/abonnements/{id}
        if ($requestMethod === 'GET' && preg_match('#^/api/abonnements/([^/]+)$#', $requestUri, $m)) {
            $id = $m[1];
            $response = $controller->show($id);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }

        // POST /api/abonnements/{id}/subscribe
        if ($requestMethod === 'POST' && preg_match('#^/api/abonnements/([^/]+)/subscribe$#', $requestUri, $m)) {
            $id = $m[1];
            $response = $controller->subscribe($id);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }

        // POST /api/abonnements/{id}/validate
        if ($requestMethod === 'POST' && preg_match('#^/api/abonnements/([^/]+)/validate$#', $requestUri, $m)) {
            $id = $m[1];
            $response = $controller->validate($id, $requestData);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }

        // GET /api/parkings/{parkingId}/abonnements - Get subscription types for a parking (Clean Architecture)
        if ($requestMethod === 'GET' && preg_match('#^/api/parkings/([^/]+)/abonnements$#', $requestUri, $m)) {
            $parkingId = $m[1];
            $response = $userSubscriptionController->getAvailableTypes($parkingId);
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        exit;
    }

    // API routes will be handled here
    // For now, return 404 for undefined routes
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'error' => 'Not Found',
        'message' => 'The requested endpoint does not exist',
        'path' => $requestUri
    ]);

} catch (Throwable $e) {
    // Global error handler
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}