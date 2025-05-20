<?php
// Read JSON file
function readJsonFile($filename) {
    $filePath = DATA_PATH . '/' . $filename; // Use defined DATA_PATH
    $cacheDir = DATA_PATH . '/cache'; // Cache directory
    // Using .php extension for cache files and adding die() can add a layer of security
    // if the cache directory somehow becomes web accessible.
    $cacheFile = $cacheDir . '/' . basename($filename) . '.cache.php';

    // Ensure cache directory exists.
    // This check might be better placed in a startup script if performance is absolutely critical,
    // but for typical shared hosting, doing it here is often acceptable.
    if (!is_dir($cacheDir)) {
        if (!@mkdir($cacheDir, 0755, true)) { // Suppress error if it already exists due to race condition
            // If it still doesn't exist after trying to create
            if (!is_dir($cacheDir)) {
                error_log("Failed to create cache directory: $cacheDir. Proceeding without cache for $filename.");
                // Fall through to read directly from JSON if cache dir fails
            }
        }
    }

    // Attempt to read from cache for specific files
    $cacheableFiles = ['menu.json', 'tables.json', 'users.json'];
    if (in_array(basename($filename), $cacheableFiles) && is_dir($cacheDir) && is_writable($cacheDir) && file_exists($cacheFile) && file_exists($filePath)) {
        if (filemtime($cacheFile) >= filemtime($filePath)) {
            $cachedContent = file_get_contents($cacheFile);
            if ($cachedContent !== false && strpos($cachedContent, '<?php die(); ?>') === 0) {
                $data = @unserialize(substr($cachedContent, strlen('<?php die(); ?>')));
                if ($data !== false) {
                    return $data; // Return data from cache
                } else {
                    error_log("Failed to unserialize cached data for $filename. Regenerating cache.");
                }
            }
        }
    }

    // Original logic to read and decode JSON if not cached or cache is invalid
    if (!file_exists($filePath)) {
        if (file_put_contents($filePath, json_encode([])) === false) {
            error_log("Failed to create JSON file: $filePath");
            return [];
        }
        chmod($filePath, 0644);
        // Cache the empty array for cacheable files
        if (in_array(basename($filename), $cacheableFiles) && is_dir($cacheDir) && is_writable($cacheDir)) {
            @file_put_contents($cacheFile, '<?php die(); ?>' . serialize([]));
        }
        return [];
    }

    $jsonData = file_get_contents($filePath);

    if ($jsonData === false) {
        error_log("Failed to read JSON file: $filePath");
        return [];
    }
    if (empty($jsonData)) {
        // Cache the empty array for cacheable files if jsonData is empty
        if (in_array(basename($filename), $cacheableFiles) && is_dir($cacheDir) && is_writable($cacheDir)) {
             @file_put_contents($cacheFile, '<?php die(); ?>' . serialize([]));
        }
        return [];
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for file $filePath: " . json_last_error_msg());
        // Optionally delete invalid cache file here if it exists
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        return [];
    }

    // Write to cache if the file is cacheable, data is valid, and cache directory is writable
    if (in_array(basename($filename), $cacheableFiles) && is_array($data) && is_dir($cacheDir) && is_writable($cacheDir)) {
        if (@file_put_contents($cacheFile, '<?php die(); ?>' . serialize($data)) === false) {
            error_log("Failed to write to cache file: $cacheFile");
        }
    }
    return $data;
}

// Write JSON file
function writeJsonFile($filename, $data) {
    $filePath = DATA_PATH . '/' . $filename; // Use defined DATA_PATH
    // Ensure data is an array or object before encoding
    if (!is_array($data) && !is_object($data)) {
        error_log("Invalid data type for JSON encoding in file: $filename");
        return false;
    }

    // Conditionally use JSON_PRETTY_PRINT (from your next requested step)
    $options = JSON_UNESCAPED_UNICODE;
    // Check if ENVIRONMENT constant is defined, default to 'development' if not for safety
    $currentEnvironment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development'; 
    if (basename($filename) !== 'orders.json' || $currentEnvironment !== 'production') {
        $options |= JSON_PRETTY_PRINT;
    }

    $jsonData = json_encode($data, $options); // Using $options here

    if ($jsonData === false) {
        error_log("JSON encode error for file $filename: " . json_last_error_msg());
        return false;
    }
    if (file_put_contents($filePath, $jsonData, LOCK_EX) === false) { // Use LOCK_EX for atomic writes
        error_log("Failed to write JSON file: $filePath");
        return false;
    }

    // If a file that was cached is written, its cache should be invalidated (deleted)
    $cacheableFiles = ['menu.json', 'tables.json', 'users.json'];
    if (in_array(basename($filename), $cacheableFiles)) {
        $cacheFile = DATA_PATH . '/cache/' . basename($filename) . '.cache.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile); // Suppress error if unlink fails for some reason
        }
    }
    return true;
}

// Generate cryptographically secure unique ID
function generateId($length = 13) { // Length of uniqid output
    // More secure and unique ID
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(ceil($length / 2)));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(ceil($length / 2)));
    } else {
        // Fallback, less secure but better than basic rand()
        return substr(str_shuffle(MD5(microtime())), 0, $length);
    }
}

// Format currency - ensure APP_NAME, CURRENCY are defined and use htmlspecialchars
function formatCurrency($amount) {
    $currencySymbol = defined('CURRENCY') ? CURRENCY : '$';
    return htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') . number_format((float)$amount, 2);
}

// Format date - use htmlspecialchars for output
function formatDate($dateString, $format = null) { // Added $format parameter
    if (empty($dateString)) {
        return 'N/A';
    }

    try {
        $date = new DateTime($dateString);
        if ($format) {
            return htmlspecialchars($date->format($format), ENT_QUOTES, 'UTF-8');
        }

        $now = new DateTime();
        $today = $now->format('Y-m-d');
        $yesterday = (new DateTime('-1 day'))->format('Y-m-d');

        if ($date->format('Y-m-d') === $today) {
            return 'Today ' . htmlspecialchars($date->format('g:i A'), ENT_QUOTES, 'UTF-8');
        } elseif ($date->format('Y-m-d') === $yesterday) {
            return 'Yesterday ' . htmlspecialchars($date->format('g:i A'), ENT_QUOTES, 'UTF-8');
        } elseif ($now->getTimestamp() - $date->getTimestamp() < 7 * 24 * 60 * 60) {
            return htmlspecialchars($date->format('l g:i A'), ENT_QUOTES, 'UTF-8');
        } else {
            return htmlspecialchars($date->format('M j, Y g:i A'), ENT_QUOTES, 'UTF-8');
        }
    } catch (Exception $e) {
        error_log("Invalid date string for formatDate: $dateString - " . $e->getMessage());
        return htmlspecialchars($dateString, ENT_QUOTES, 'UTF-8'); // Return original if invalid
    }
}

// Set flash message
function setFlashMessage($type, $message) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get flash message (and clear it) - use htmlspecialchars for output
function getFlashMessage() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        // Sanitize message before returning for display
        $flash['message'] = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        $flash['type'] = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        return $flash;
    }
    return null;
}


// Check if user has permission
function hasPermission($role, $permission) {
    // Define permissions for each role
    $permissions = [
        'admin'   => ['dashboard', 'orders', 'order_create', 'order_update', 'menu', 'menu_add', 'menu_edit', 'tables', 'payments', 'reports', 'users', 'profile'],
        'manager' => ['dashboard', 'orders', 'order_create', 'order_update', 'menu', 'menu_add', 'menu_edit', 'tables', 'payments', 'reports', 'profile'],
        'cashier' => ['orders', 'order_create', 'order_update', 'payments', 'profile'],
        'waiter'  => ['orders', 'order_create', 'order_update', 'tables', 'profile'], // Waiters might need to update orders too
        'kitchen' => ['orders', 'profile'] // Kitchen staff might only view orders
    ];

    if (isset($permissions[$role]) && in_array($permission, $permissions[$role])) {
        return true;
    }
    return false;
}

// Get current user
function getCurrentUser() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Get status class for order status
function getStatusClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get status class for payment status
function getPaymentStatusClass($status) {
    switch (strtolower($status)) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'refunded':
            return 'bg-red-100 text-red-800'; // Added refunded status
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get class for user role
function getRoleClass($role) {
    switch (strtolower($role)) {
        case 'admin':
            return 'bg-purple-100 text-purple-800';
        case 'manager':
            return 'bg-blue-100 text-blue-800';
        case 'cashier':
            return 'bg-green-100 text-green-800';
        case 'waiter':
            return 'bg-yellow-100 text-yellow-800';
        case 'kitchen':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Calculate order total - UPDATED FOR CUSTOM ITEMS
function calculateOrderTotal($orderItems, $menuItemsAll, $discount = 0) {
    $subtotal = 0;

    // Ensure $orderItems and $menuItemsAll are arrays to prevent errors
    if (!is_array($orderItems)) {
        error_log("calculateOrderTotal: \$orderItems is not an array.");
        $orderItems = []; // Default to empty array to prevent further errors
    }
    if (!is_array($menuItemsAll)) {
        error_log("calculateOrderTotal: \$menuItemsAll (full menu data) is not an array.");
        $menuItemsAll = []; // Default to empty array
    }

    foreach ($orderItems as $item) {
        $itemQuantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        if ($itemQuantity <= 0) {
            continue; // Skip items with no or invalid quantity
        }

        $itemPrice = 0;

        // Check if it's a custom item with its own price
        // This part correctly handles the custom item structure
        if (isset($item['is_custom']) && $item['is_custom'] === true && isset($item['custom_price'])) {
            $itemPrice = (float)$item['custom_price'];
        }
        // Else, if it's a regular menu item, look up its price
        else if (isset($item['menu_item_id'])) {
            $menuItemDetails = null;
            foreach ($menuItemsAll as $mi) { // $menuItemsAll is the complete list of menu items from menu.json
                if (isset($mi['id']) && $mi['id'] === $item['menu_item_id']) {
                    $menuItemDetails = $mi;
                    break;
                }
            }

            if ($menuItemDetails && isset($menuItemDetails['price'])) {
                $itemPrice = (float)$menuItemDetails['price'];
            } else {
                 // Log an error if a menu item's price cannot be found
                 error_log("calculateOrderTotal: Price not found for menu_item_id: " . ($item['menu_item_id'] ?? 'UNKNOWN_ID') . ". Item details: " . print_r($item, true));
            }
        } else {
            // Log an error if the item is neither custom nor has a menu_item_id
            error_log("calculateOrderTotal: Item is neither custom nor has a menu_item_id. Item details: " . print_r($item, true));
        }
        
        $subtotal += $itemPrice * $itemQuantity;
    }

    $numericDiscount = (float) $discount;
    // Ensure TAX_RATE is defined, otherwise default to 0
    $taxRate = defined('TAX_RATE') ? (float)TAX_RATE : 0;

    $discountedSubtotal = max(0, $subtotal - $numericDiscount);
    $tax = $discountedSubtotal * ($taxRate / 100);
    $total = $discountedSubtotal + $tax;

    return [
        'subtotal' => $subtotal,
        'discount' => $numericDiscount,
        'tax' => $tax,
        'total' => $total
    ];
}


// --- CSRF Protection ---
function generateCsrfToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Token is valid, clear it for one-time use (optional, but good practice)
        // unset($_SESSION['csrf_token']); // Or generate a new one
        return true;
    }
    return false;
}

// --- Password Complexity ---
function isPasswordStrong($password) {
    $minLength = 8;
    $hasUpperCase = preg_match('/[A-Z]/', $password);
    $hasLowerCase = preg_match('/[a-z]/', $password);
    $hasDigit = preg_match('/\d/', $password);
    $hasSpecialChar = preg_match('/[^A-Za-z\d]/', $password); // Checks for any non-alphanumeric character

    if (strlen($password) < $minLength) {
        return "Password must be at least $minLength characters long.";
    }
    if (!$hasUpperCase) {
        return "Password must include at least one uppercase letter.";
    }
    if (!$hasLowerCase) {
        return "Password must include at least one lowercase letter.";
    }
    if (!$hasDigit) {
        return "Password must include at least one digit.";
    }
    // if (!$hasSpecialChar) { // Optional: Enable if you want to enforce special characters
    //     return "Password must include at least one special character.";
    // }
    return true; // Password is strong
}

// --- Session Management ---
function checkSessionTimeout() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $timeout_duration = defined('SESSION_TIMEOUT_DURATION') ? SESSION_TIMEOUT_DURATION : 1800; // Default 30 mins

    if (isset($_SESSION['user'])) {
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout_duration)) {
            // Session has expired
            session_unset();
            session_destroy();
            setFlashMessage('error', 'Your session has timed out. Please log in again.');
            // Adjust path to login.php if functions.php is in 'includes' and login.php is in 'modules/auth'
            $loginPath = (strpos($_SERVER['PHP_SELF'], '/modules/') === false) ? 'modules/auth/login.php' : '../auth/login.php';
            if (defined('BASE_URL')) { // If you have a BASE_URL defined in config.php
                header('Location: ' . BASE_URL . 'modules/auth/login.php');
            } else {
                 // Basic relative path adjustment - might need refinement based on your exact structure
                $pathPrefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'includes') ? '../' : '';
                header('Location: ' . $pathPrefix . 'modules/auth/login.php');
            }
            exit;
        }
        $_SESSION['login_time'] = time(); // Reset timeout timer on activity
    }
}

// Call this at the beginning of pages that require login
function ensureLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user'])) {
        setFlashMessage('error', 'You need to log in to access this page.');
        // Adjust path to login.php
        $loginPath = (strpos($_SERVER['PHP_SELF'], '/modules/') === false) ? 'modules/auth/login.php' : '../auth/login.php';
         if (defined('BASE_URL')) {
            header('Location: ' . BASE_URL . 'modules/auth/login.php');
        } else {
            $pathPrefix = (basename(dirname($_SERVER['PHP_SELF'])) === 'includes') ? '../' : '';
            header('Location: ' . $pathPrefix . 'modules/auth/login.php');
        }
        exit;
    }
    checkSessionTimeout(); // Also check for timeout
}

// Sanitize output
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

?>