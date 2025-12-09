<?php

declare(strict_types=1);

/**
 * JSON File Storage Configuration
 * Returns a configured JsonFileService instance
 * Clean Architecture - Configuration Layer
 */

use Infrastructure\NoSql\JsonFileService;

try {
    // Get data path from environment or use default
    $dataPath = getenv('JSON_DATA_PATH') ?: __DIR__ . '/../data/json/';
    
    // Create JsonFileService instance
    $jsonFileService = new JsonFileService($dataPath);
    
    return $jsonFileService;

} catch (Exception $e) {
    // Log the error (in production, use a proper logger)
    error_log('JSON file service initialization failed: ' . $e->getMessage());

    // In development, show detailed error
    // In production, you might want to show a generic error
    if (getenv('APP_ENV') === 'production') {
        throw new RuntimeException('JSON file service initialization failed. Please contact support.');
    }

    // Re-throw with details in development
    throw new RuntimeException(
        'JSON file service initialization failed: ' . $e->getMessage(),
        (int) $e->getCode(),
        $e
    );
}