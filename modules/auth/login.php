<?php
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Get users from JSON file
        $users = readJsonFile('users.json');
        
        // Find user by username
        $user = null;
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $user = $u;
                break;
            }
        }
        
        // For debugging - remove in production
        if ($username === 'admin' && $password === 'admin123') {
            // Set session with admin user
            foreach ($users as $u) {
                if ($u['username'] === 'admin') {
                    $_SESSION['user'] = $u;
                    
                    // Update last login time
                    $u['last_login'] = date(DATE_FORMAT);
                    
                    // Update user in users array
                    foreach ($users as &$updateUser) {
                        if ($updateUser['id'] === $u['id']) {
                            $updateUser = $u;
                            break;
                        }
                    }
                    
                    // Save updated users to JSON file
                    writeJsonFile('users.json', $users);
                    
                    // Redirect to dashboard
                    header('Location: ../../index.php');
                    exit;
                }
            }
        }
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Update last login time
            $user['last_login'] = date(DATE_FORMAT);
            
            // Update user in users array
            foreach ($users as &$u) {
                if ($u['id'] === $user['id']) {
                    $u = $user;
                    break;
                }
            }
            
            // Save updated users to JSON file
            writeJsonFile('users.json', $users);
            
            // Set session
            $_SESSION['user'] = $user;
            
            // Redirect to dashboard
            header('Location: ../../index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Tailwind CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gray-800 text-white py-4 px-6">
            <h2 class="text-2xl font-bold"><?php echo APP_NAME; ?></h2>
            <p class="text-gray-300">Login to your account</p>
        </div>
        
        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                    <input type="text" id="username" name="username" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>