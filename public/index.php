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
