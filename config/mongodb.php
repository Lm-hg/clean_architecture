<?php

declare(strict_types=1);

/**
 * MongoDB Configuration
 * Returns a configured MongoDB client instance
 */

try {
    // Check if MongoDB extension is available
    if (!class_exists('MongoDB\Driver\Manager')) {
        error_log('MongoDB extension not installed - using fallback storage');
        return null;
    }
    
    // Get MongoDB credentials from environment variables
    $mongoHost = getenv('MONGO_HOST') ?: 'localhost';
    $mongoUser = getenv('MONGO_USER') ?: 'root';
    $mongoPassword = getenv('MONGO_PASSWORD') ?: '';
    $mongoDb = getenv('MONGO_DB') ?: 'parking_db';
    $mongoPort = getenv('MONGO_PORT') ?: '27017';

    // Build MongoDB connection string
    $connectionString = sprintf(
        'mongodb://%s:%s@%s:%s',
        $mongoUser,
        $mongoPassword,
        $mongoHost,
        $mongoPort
    );

    // MongoDB client options
    $options = [
        'connectTimeoutMS' => 5000,
        'serverSelectionTimeoutMS' => 5000,
    ];

    // Create MongoDB client
    $mongoClient = new MongoDB\Driver\Manager($connectionString, $options);

    // Test connection by pinging the server
    $command = new MongoDB\Driver\Command(['ping' => 1]);
    $mongoClient->executeCommand('admin', $command);

    // Return the MongoDB client
    return $mongoClient;

} catch (MongoDB\Driver\Exception\Exception $e) {
    // Log the error (in production, use a proper logger)
    error_log('MongoDB connection failed: ' . $e->getMessage());
    return null;
} catch (\Throwable $e) {
    // Any other error (extension not loaded, etc.)
    error_log('MongoDB not available: ' . $e->getMessage());
    return null;
}
