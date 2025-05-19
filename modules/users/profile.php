<?php
// Get current user
$currentUser = getCurrentUser();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get users
    $users = readJsonFile('users.json');
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    // If changing password
    if (!empty($newPassword) || !empty($confirmPassword)) {
        // Verify current password
        $passwordVerified = false;
        foreach ($users as $user) {
            if ($user['id'] === $currentUser['id']) {
                if (password_verify($currentPassword, $user['password'])) {
                    $passwordVerified = true;
                }
                break;
            }
        }
        
        if (!$passwordVerified) {
            $errors[] = 'Current password is incorrect.';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        // Update user data
        foreach ($users as &$user) {
            if ($user['id'] === $currentUser['id']) {
                $user['name'] = $name;
                
                // Update password if provided
                if (!empty($newPassword)) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                
                break;
            }
        }
        
        // Save changes
        if (writeJsonFile('users.json', $users)) {
            // Update session
            $_SESSION['user']['name'] = $name;
            
            setFlashMessage('success', 'Profile updated successfully.');
            header('Location: index.php?page=profile');
            exit;
        } else {
            $errors[] = 'Failed to save changes.';
        }
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">My Profile</h1>
    
    <?php
    // Display flash message if any
    $flashMessage = getFlashMessage();
    if ($flashMessage) {
        $alertClass = $flashMessage['type'] === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
    ?>
    <div class="<?php echo $alertClass; ?> border-l-4 p-4 mb-6" role="alert">
        <p><?php echo $flashMessage['message']; ?></p>
    </div>
    <?php } ?>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Profile Information -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Profile Information</h2>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        <p class="text-xs text-gray-500 mt-1">Username cannot be changed.</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <input type="text" id="role" value="<?php echo ucfirst($currentUser['role']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    
                    <h3 class="text-md font-semibold mb-3 mt-6">Change Password</h3>
                    
                    <div class="mb-4">
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters.</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Account Information</h2>
                
                <div class="flex items-center justify-center mb-4">
                    <div class="w-24 h-24 rounded-full bg-blue-500 flex items-center justify-center text-white text-3xl font-bold">
                        <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></h3>
                    <p class="text-gray-500"><?php echo ucfirst($currentUser['role']); ?></p>
                </div>
                
                <div class="border-t pt-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Username:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                    </div>
                    
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-600">Account Created:</span>
                        <span class="font-medium"><?php echo isset($currentUser['created_at']) ? $currentUser['created_at'] : 'N/A'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>