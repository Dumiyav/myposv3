<?php
// Get users
$users = readJsonFile('users.json');

// Process user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    
    // Don't allow deleting the current user
    if ($userId === $_SESSION['user']['id']) {
        setFlashMessage('error', 'You cannot delete your own account.');
    } else {
        // Remove user
        $newUsers = [];
        foreach ($users as $user) {
            if ($user['id'] !== $userId) {
                $newUsers[] = $user;
            }
        }
        
        // Save changes
        if (writeJsonFile('users.json', $newUsers)) {
            setFlashMessage('success', 'User deleted successfully.');
        } else {
            setFlashMessage('error', 'Failed to delete user.');
        }
    }
    
    // Redirect to refresh
    header('Location: index.php?page=users');
    exit;
}
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">User Management</h1>
        
        <a href="index.php?page=users&action=add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add User
        </a>
    </div>
    
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
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 text-left">Username</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Role</th>
                        <th class="px-4 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2"><?php echo $user['username']; ?></td>
                        <td class="px-4 py-2"><?php echo $user['name']; ?></td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo getRoleClass($user['role']); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <div class="flex justify-center space-x-2">
                                <a href="index.php?page=users&action=edit&id=<?php echo $user['id']; ?>" class="text-blue-500 hover:text-blue-700" title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </a>
                                
                                <?php if ($user['id'] !== $_SESSION['user']['id']): ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this user?');" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>