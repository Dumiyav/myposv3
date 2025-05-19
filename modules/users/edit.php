<?php
// Get users
$users = readJsonFile('users.json');

// Get user ID from URL
$userId = isset($_GET['id']) ? $_GET['id'] : null;
$user = null;

// Find user
if ($userId) {
    foreach ($users as $u) {
        if ($u['id'] === $userId) {
            $user = $u;
            break;
        }
    }
}

// If user not found, redirect to users list
if (!$user) {
    setFlashMessage('error', 'User not found.');
    header('Location: index.php?page=users');
    exit;
}

// Define available roles
$roles = ['admin', 'manager', 'cashier', 'waiter', 'kitchen'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } else {
        // Check if username already exists (excluding current user)
        foreach ($users as $u) {
            if ($u['id'] !== $userId && strtolower($u['username']) === strtolower($username)) {
                $errors[] = 'Username already exists.';
                break;
            }
        }
    }
    
    // Password is optional when editing
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($role) || !in_array($role, $roles)) {
        $errors[] = 'Please select a valid role.';
    }
    
    // If no errors, update user
    if (empty($errors)) {
        // Update user data
        foreach ($users as &$u) {
            if ($u['id'] === $userId) {
                $u['username'] = $username;
                $u['name'] = $name;
                $u['role'] = $role;
                
                // Update password if provided
                if (!empty($password)) {
                    $u['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                break;
            }
        }
        
        // Save changes
        if (writeJsonFile('users.json', $users)) {
            // If current user was updated, update session
            if ($userId === $_SESSION['user']['id']) {
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['role'] = $role;
            }
            
            setFlashMessage('success', 'User updated successfully.');
            header('Location: index.php?page=users');
            exit;
        } else {
            $errors[] = 'Failed to save changes.';
        }
    }
}
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="index.php?page=users" class="mr-4">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold">Edit User</h1>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password.</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $user['role'] === $r ? 'selected' : ''; ?>>
                            <?php echo ucfirst($r); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="index.php?page=users" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>