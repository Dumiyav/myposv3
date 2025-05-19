<?php
// Get menu items
$menuItems = readJsonFile('menu.json');

// Group by category
$menuByCategory = [];
foreach ($menuItems as $item) {
    if (!isset($menuByCategory[$item['category']])) {
        $menuByCategory[$item['category']] = [];
    }
    $menuByCategory[$item['category']][] = $item;
}

// Sort categories alphabetically
ksort($menuByCategory);

// Process item deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['item_id'])) {
    $itemId = $_POST['item_id'];
    
    // Remove item
    $newMenuItems = [];
    foreach ($menuItems as $item) {
        if ($item['id'] !== $itemId) {
            $newMenuItems[] = $item;
        }
    }
    
    // Save changes
    if (writeJsonFile('menu.json', $newMenuItems)) {
        setFlashMessage('success', 'Menu item deleted successfully.');
    } else {
        setFlashMessage('error', 'Failed to delete menu item.');
    }
    
    // Redirect to refresh
    header('Location: index.php?page=menu');
    exit;
}
?>

<div class="container mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Menu Management</h1>
        
        <a href="index.php?page=menu_add" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add Menu Item
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
    
    <!-- Search Box -->
    <div class="mb-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input type="text" id="menuSearch" placeholder="Search menu items..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
    </div>
    
    <?php if (empty($menuItems)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 text-center">
        <p class="text-gray-500">No menu items found.</p>
        <a href="index.php?page=menu_add" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
            Add Menu Item
        </a>
    </div>
    <?php else: ?>
    <?php foreach ($menuByCategory as $category => $items): ?>
    <div class="menu-category mb-8">
        <h2 class="text-xl font-semibold mb-4 capitalize"><?php echo $category; ?></h2>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Description</th>
                            <th class="px-4 py-2 text-right">Price</th>
                            <th class="px-4 py-2 text-right">Stock</th>
                            <th class="px-4 py-2 text-center">Status</th>
                            <th class="px-4 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr class="border-b hover:bg-gray-50 menu-item" data-name="<?php echo $item['name']; ?>" data-code="<?php echo $item['code'] ?? ''; ?>" data-category="<?php echo $category; ?>">
                            <td class="px-4 py-2 font-mono text-sm"><?php echo isset($item['code']) ? htmlspecialchars($item['code']) : '-'; ?></td>
                            <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="px-4 py-2 text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['price']); ?></td>
                            <td class="px-4 py-2 text-right">
                                <?php if (isset($item['stock'])): ?>
                                <span class="<?php echo $item['stock'] <= 5 ? ($item['stock'] > 0 ? 'text-orange-500' : 'text-red-500') : ''; ?>">
                                    <?php echo $item['stock']; ?>
                                </span>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $item['available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $item['available'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="index.php?page=menu_edit&id=<?php echo $item['id']; ?>" class="text-blue-500 hover:text-blue-700" title="Edit">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this menu item?');" class="inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700" title="Delete">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('menuSearch');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const menuItems = document.querySelectorAll('.menu-item');
            const menuCategories = document.querySelectorAll('.menu-category');
            
            menuItems.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                const code = item.getAttribute('data-code') ? item.getAttribute('data-code').toLowerCase() : '';
                const category = item.getAttribute('data-category').toLowerCase();
                
                if (name.includes(searchTerm) || code.includes(searchTerm) || category.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Hide empty categories
            menuCategories.forEach(category => {
                const visibleItems = category.querySelectorAll('.menu-item[style=""]').length;
                if (visibleItems === 0) {
                    category.style.display = 'none';
                } else {
                    category.style.display = '';
                }
            });
        });
    });
</script>