<?php
/**
 * Bootstrap file for PHPUnit tests
 * Ensures all required classes are loaded
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Ensure UserApiFunctionalTestHelper is loaded if it exists
$helperFile = __DIR__ . '/functional/User/UserApiFunctionalTestHelper.php';
if (file_exists($helperFile) && !class_exists('Tests\Functional\User\UserApiFunctionalTestHelper')) {
    require_once $helperFile;
}

