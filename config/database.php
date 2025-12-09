<?php

declare(strict_types=1);

/**
 * Database Configuration
 * Returns a configured PDO instance for PostgreSQL
 */

try {
    // Get database credentials from environment variables
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'parking_db';
    $dbUser = getenv('DB_USER') ?: 'postgres';
    $dbPassword = getenv('DB_PASSWORD') ?: 'Gabie28';
    $dbPort = getenv('DB_PORT') ?: '5432';

    // Build PostgreSQL DSN (Data Source Name)
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbHost,
        $dbPort,
        $dbName
    );

    // PDO options for security and error handling
    $options = [
        // Use exceptions for error handling
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // Return associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Disable emulated prepared statements for better security
        PDO::ATTR_EMULATE_PREPARES => false,

        // Set connection timeout (in seconds)
        PDO::ATTR_TIMEOUT => 5,

        // Persistent connections (optional, use with caution)
        // PDO::ATTR_PERSISTENT => false,
    ];

    // Create PDO instance
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);

    // Set character encoding to UTF-8
    $pdo->exec("SET NAMES 'UTF8'");

    // Return the configured PDO instance
    return $pdo;

} catch (PDOException $e) {
    // Log the error (in production, use a proper logger)
    error_log('Database connection failed: ' . $e->getMessage());

    // In development, show detailed error
    // In production, you might want to show a generic error
    if (getenv('APP_ENV') === 'production') {
        throw new RuntimeException('Database connection failed. Please contact support.');
    }

    // Re-throw with details in development
    throw new RuntimeException(
        'Database connection failed: ' . $e->getMessage(),
        (int) $e->getCode(),
        $e
    );
}
