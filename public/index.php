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
define('BASE_PATH', dirname(__DIR__));

// Load Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Load environment variables (if using vlucas/phpdotenv, otherwise manual loading)
// For now, we'll use getenv() which works with docker-compose environment variables

/**
 * Helper function to get request body (supports testing via $GLOBALS)
 */
function getRequestBody(): string
{
    // Support for testing: if TEST_REQUEST_BODY is set, use it
    if (isset($GLOBALS['TEST_REQUEST_BODY'])) {
        return $GLOBALS['TEST_REQUEST_BODY'];
    }
    
    // Otherwise, read from php://input
    return file_get_contents('php://input') ?: '';
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

        // Dependencies
        $pdo = require BASE_PATH . '/config/database.php';
        $mongo = require BASE_PATH . '/config/mongodb.php';

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
        // POST /api/abonnements
        if ($requestMethod === 'POST' && preg_match('#^/api/abonnements$#', $requestUri)) {
            $response = $controller->create($requestData);
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

        // GET /api/parkings/{parkingId}/abonnements
        if ($requestMethod === 'GET' && preg_match('#^/api/parkings/([^/]+)/abonnements$#', $requestUri, $m)) {
            $parkingId = $m[1];
            $response = $controller->indexForParking($parkingId);
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
