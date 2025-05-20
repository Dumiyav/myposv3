<?php
// Get existing users
$users = readJsonFile('users.json');

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
        // Check if username already exists
        foreach ($users as $user) {
            if (strtolower($user['username']) === strtolower($username)) {
                $errors[] = 'Username already exists.';
                break;
            }
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($role) || !in_array($role, $roles)) {
        $errors[] = 'Please select a valid role.';
    }
    
    // If no errors, create user
    if (empty($errors)) {
        // Create new user
        $newUser = [
            'id' => generateId(),
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'role' => $role,
            'created_at' => date(DATE_FORMAT)
        ];
        
        // Add to users array
        $users[] = $newUser;
        
        // Save changes
        if (writeJsonFile('users.json', $users)) {
            setFlashMessage('success', 'User created successfully.');
            header('Location: index.php?page=users');
            exit;
        } else {
            $errors[] = 'Failed to save user.';
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
        <h1 class="text-2xl font-bold">Add User</h1>
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters.</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select role</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo (isset($_POST['role']) && $_POST['role'] === $r) ? 'selected' : ''; ?>>
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
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>