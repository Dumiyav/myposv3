<?php
// Read JSON file
function readJsonFile($filename) {
    $filePath = __DIR__ . '/../data/' . $filename;
    
    if (!file_exists($filePath)) {
        return [];
    }
    
    $jsonData = file_get_contents($filePath);
    
    if (empty($jsonData)) {
        return [];
    }
    
    return json_decode($jsonData, true) ?: [];
}

// Write JSON file
function writeJsonFile($filename, $data) {
    $filePath = __DIR__ . '/../data/' . $filename;
    $jsonData = json_encode($data, JSON_PRETTY_PRINT);
    
    return file_put_contents($filePath, $jsonData) !== false;
}

// Generate unique ID
function generateId($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $id = '';
    
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $id;
}

// Format currency
function formatCurrency($amount) {
    return CURRENCY . number_format($amount, 2);
}

// Format date
function formatDate($dateString) {
    if (empty($dateString)) {
        return '';
    }
    
    $date = strtotime($dateString);
    
    if ($date === false) {
        return $dateString;
    }
    
    // If date is today, show only time
    if (date('Y-m-d', $date) === date('Y-m-d')) {
        return 'Today ' . date('g:i A', $date);
    }
    
    // If date is yesterday, show "Yesterday"
    if (date('Y-m-d', $date) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday ' . date('g:i A', $date);
    }
    
    // If date is within the last 7 days, show day name
    if (time() - $date < 7 * 24 * 60 * 60) {
        return date('l g:i A', $date);
    }
    
    // Otherwise show full date
    return date('M j, Y g:i A', $date);
}

// Set flash message
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}

// Check if user has permission
function hasPermission($role, $permission) {
    // Define permissions for each role
    $permissions = [
        'admin' => ['dashboard', 'orders', 'menu', 'tables', 'payments', 'reports', 'users'],
        'manager' => ['dashboard', 'orders', 'menu', 'tables', 'payments', 'reports'],
        'cashier' => ['dashboard', 'orders', 'payments'],
        'waiter' => ['dashboard', 'orders', 'tables'],
        'kitchen' => ['dashboard', 'orders']
    ];
    
    // Check if role exists and has the permission
    if (isset($permissions[$role]) && in_array($permission, $permissions[$role])) {
        return true;
    }
    
    return false;
}

// Get current user
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

// Get status class for order status
function getStatusClass($status) {
    switch ($status) {
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
    switch ($status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'refunded':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get class for user role
function getRoleClass($role) {
    switch ($role) {
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

// Calculate order total
function calculateOrderTotal($orderItems, $menuItems, $discount = 0) {
    $subtotal = 0;
    
    foreach ($orderItems as $item) {
        $menuItem = null;
        foreach ($menuItems as $mi) {
            if ($mi['id'] === $item['menu_item_id']) {
                $menuItem = $mi;
                break;
            }
        }
        
        if ($menuItem) {
            $subtotal += $menuItem['price'] * $item['quantity'];
        }
    }
    
    $discountedSubtotal = max(0, $subtotal - $discount);
    $tax = $discountedSubtotal * (TAX_RATE / 100);
    $total = $discountedSubtotal + $tax;
    
    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'tax' => $tax,
        'total' => $total
    ];
}
?>