<?php
// Application configuration

// Application path
define('APP_PATH', dirname(__FILE__));
define('DATA_PATH', APP_PATH . '/data');

// Application name
define('APP_NAME', 'ViduraMedix POS'); // Consider using htmlspecialchars when displaying

// Tax rate (percentage)
define('TAX_RATE', 0); // Example: 10 for 10%

// Currency symbol
define('CURRENCY', 'Rs.'); // Example: '$'

// Date format
define('DATE_FORMAT', 'Y-m-d H:i:s');

// Session Timeout (in seconds) - e.g., 30 minutes
define('SESSION_TIMEOUT_DURATION', 1800); // 30 * 60

// --- Production/Development Environment Settings ---
// Set this to 'development' or 'production'
define('ENVIRONMENT', 'production'); // CHANGED 'development' TO 'production' FOR LIVE SERVER

if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    // Consider logging errors to a file in production
    ini_set('log_errors', '1'); // ENABLED
    // Replace with an actual path on your server if APP_PATH . '/php-error.log' is not suitable.
    // Ensure the web server has write permissions to this file/path.
    ini_set('error_log', APP_PATH . '/php-error.log'); // CONFIGURED: logs to myposv04/php-error.log

    // Secure session cookie settings for production
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Ensure HTTPS is enabled on your server
    ini_set('session.use_only_cookies', 1); // Prevent session ID in URL
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}


// Ensure data directory exists
if (!file_exists(DATA_PATH)) {
    if (!mkdir(DATA_PATH, 0755, true)) { // Set appropriate permissions
        die("Failed to create data directory. Please check permissions.");
    }
}

// Initialize data files if they don't exist
$dataFiles = ['menu.json', 'orders.json', 'tables.json', 'users.json'];

foreach ($dataFiles as $file) {
    $filePath = DATA_PATH . '/' . $file;
    if (!file_exists($filePath)) {
        if (file_put_contents($filePath, json_encode([])) === false) {
            die("Failed to create data file: $file. Please check permissions.");
        }
        chmod($filePath, 0644); // Set appropriate permissions
    }
}

// It's better to create the first admin user manually or via a secure setup script
// than having default credentials in code.
// If users.json is empty, you might want to redirect to a setup page or show an error.
$usersFile = DATA_PATH . '/users.json';
$usersData = file_get_contents($usersFile);
if ($usersData !== false) {
    $users = json_decode($usersData, true);
    if (empty($users)) {
        // Optionally, create a very strong, random initial password here
        // and prompt the admin to change it on first login.
        // Or, better yet, require manual creation or a setup script.
        // For now, we'll leave it to be created via the user interface if an admin role is needed.
        // echo "Warning: users.json is empty. Please create an admin user.";
    }
} else {
    die("Failed to read users.json. Please check file and permissions.");
}


// Initialize default tables if tables.json is empty (Can be kept or removed based on preference)
$tablesFile = DATA_PATH . '/tables.json';
$tablesData = file_get_contents($tablesFile);
if ($tablesData !== false) {
    $tables = json_decode($tablesData, true);
    if (empty($tables)) {
        $defaultTables = [];
        for ($i = 1; $i <= 10; $i++) {
            $defaultTables[] = [
                'id' => uniqid(), // uniqid is better than rand for IDs
                'name' => 'Table ' . $i,
                'capacity' => 4,
                'status' => 'available'
            ];
        }
        if (file_put_contents($tablesFile, json_encode($defaultTables, JSON_PRETTY_PRINT)) === false) {
            die("Failed to write default tables to tables.json. Please check permissions.");
        }
    }
} else {
    die("Failed to read tables.json. Please check file and permissions.");
}

?>