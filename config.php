<?php
// Application configuration

// Application path
define('APP_PATH', dirname(__FILE__));
define('DATA_PATH', APP_PATH . '/data');

// Application name
define('APP_NAME', 'ViduraMedix POS');

// Tax rate (percentage)
define('TAX_RATE', 0);

// Currency symbol
define('CURRENCY', 'Rs.');

// Date format
define('DATE_FORMAT', 'Y-m-d H:i:s');

// Ensure data directory exists
if (!file_exists(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

// Initialize data files if they don't exist
$dataFiles = ['menu.json', 'orders.json', 'tables.json', 'users.json'];

foreach ($dataFiles as $file) {
    $filePath = DATA_PATH . '/' . $file;
    if (!file_exists($filePath)) {
        file_put_contents($filePath, json_encode([]));
        chmod($filePath, 0644);
    }
}

// Initialize default admin user if users.json is empty
$usersFile = DATA_PATH . '/users.json';
$users = json_decode(file_get_contents($usersFile), true);

if (empty($users)) {
    $defaultAdmin = [
        'id' => uniqid(),
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'name' => 'Administrator',
        'role' => 'admin',
        'last_login' => date(DATE_FORMAT)
    ];
    
    file_put_contents($usersFile, json_encode([$defaultAdmin]));
}

// Initialize default tables if tables.json is empty
$tablesFile = DATA_PATH . '/tables.json';
$tables = json_decode(file_get_contents($tablesFile), true);

if (empty($tables)) {
    $defaultTables = [];
    for ($i = 1; $i <= 10; $i++) {
        $defaultTables[] = [
            'id' => uniqid(),
            'name' => 'Table ' . $i,
            'capacity' => 4,
            'status' => 'available'
        ];
    }
    
    file_put_contents($tablesFile, json_encode($defaultTables));
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);