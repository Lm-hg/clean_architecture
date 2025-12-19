<?php
/**
 * Bootstrap file for PHPUnit tests
 * Ensures all required classes are loaded
 */

// Define BASE_PATH for tests
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Ensure UserApiFunctionalTestHelper is loaded if it exists
$helperFile = __DIR__ . '/functional/User/UserApiFunctionalTestHelper.php';
if (file_exists($helperFile) && !class_exists('Tests\Functional\User\UserApiFunctionalTestHelper')) {
    require_once $helperFile;
}

