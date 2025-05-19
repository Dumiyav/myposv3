<?php
// Get menu items
$menuItems = readJsonFile('menu.json');

// Get item ID from URL
$itemId = isset($_GET['id']) ? $_GET['id'] : null;
$item = null;

// Find item
if ($itemId) {
    foreach ($menuItems as $mi) {
        if ($mi['id'] === $itemId) {
            $item = $mi;
            break;
        }
    }
}

// If item not found, redirect to menu list
if (!$item) {
    setFlashMessage('error', 'Menu item not found.');
    header('Location: index.php?page=menu');
    exit;
}

// Get existing categories
$categories = [];
$categoryCodeCounts = []; // To track the count of items in each category for code generation
foreach ($menuItems as $mi) {
    $category = $mi['category'];
    if (!in_array($category, $categories)) {
        $categories[] = $category;
        $categoryCodeCounts[$category] = 0;
    }
    
    // Count items per category for code generation
    if (isset($mi['category'])) {
        if (!isset($categoryCodeCounts[$mi['category']])) {
            $categoryCodeCounts[$mi['category']] = 1;
        } else {
            $categoryCodeCounts[$mi['category']]++;
        }
    }
}
sort($categories);

// Function to generate a unique item code
function generateItemCode($category, $name, $existingCodes, $currentCode = '') {
    global $categoryCodeCounts;
    
    // Get category prefix (first 3 letters, uppercase)
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $category), 0, 3));
    if (empty($prefix)) {
        $prefix = "ITM"; // Default if category doesn't yield usable characters
    }
    
    // Get the current count for this category
    $count = isset($categoryCodeCounts[$category]) ? $categoryCodeCounts[$category] + 1 : 1;
    
    // Generate code with sequential number
    $code = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    
    // Ensure code is unique
    $originalCode = $code;
    $counter = 1;
    while (in_array($code, $existingCodes) && $code !== $currentCode) {
        $code = $originalCode . $counter;
        $counter++;
    }
    
    return $code;
}

// Get all existing codes (excluding current item)
$existingCodes = [];
foreach ($menuItems as $mi) {
    if (isset($mi['code']) && $mi['id'] !== $itemId) {
        $existingCodes[] = $mi['code'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $available = isset($_POST['available']) ? true : false;
    
    // Use new category if provided
    if (!empty($newCategory)) {
        $category = $newCategory;
    }
    
    // Auto-generate code if empty
    if (empty($code) && !empty($category) && !empty($name)) {
        $code = generateItemCode($category, $name, $existingCodes);
    }
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Item name is required.';
    }
    
    if (empty($code)) {
        $errors[] = 'Item code is required.';
    } else {
        // Check if code already exists (excluding current item)
        foreach ($menuItems as $mi) {
            if ($mi['id'] !== $itemId && isset($mi['code']) && strtoupper($mi['code']) === strtoupper($code)) {
                $errors[] = 'Item code already exists.';
                break;
            }
        }
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required.';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than zero.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock quantity cannot be negative.';
    }
    
    // If no errors, update menu item
    if (empty($errors)) {
        // Update menu item
        foreach ($menuItems as &$mi) {
            if ($mi['id'] === $itemId) {
                $mi['name'] = $name;
                $mi['code'] = strtoupper($code);
                $mi['category'] = $category;
                $mi['description'] = $description;
                $mi['price'] = $price;
                $mi['stock'] = $stock;
                $mi['available'] = $available;
                break;
            }
        }
        
        // Save changes
        if (writeJsonFile('menu.json', $menuItems)) {
            setFlashMessage('success', 'Menu item updated successfully.');
            header('Location: index.php?page=menu');
            exit;
        } else {
            $errors[] = 'Failed to save changes.';
        }
    }
}
?>

<div class="container mx-auto">
    <div class="flex items-center mb-6">
        <a href="index.php?page=menu" class="mr-4">
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-2xl font-bold">Edit Menu Item</h1>
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
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Item Name *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $item['category'] === $cat ? 'selected' : ''; ?>>
                            <?php echo ucfirst($cat); ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="new">+ Add New Category</option>
                    </select>
                </div>
                
                <div id="newCategoryContainer" style="display: none;">
                    <label for="new_category" class="block text-sm font-medium text-gray-700 mb-1">New Category *</label>
                    <input type="text" id="new_category" name="new_category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Item Code/ID *</label>
                    <div class="relative">
                        <input type="text" id="code" name="code" value="<?php echo isset($item['code']) ? htmlspecialchars($item['code']) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" readonly>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <button type="button" id="regenerateCode" class="text-blue-500 hover:text-blue-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Unique identifier for the item</p>
                </div>
                
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($item['description']); ?></textarea>
                </div>
                
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?php echo CURRENCY; ?></span>
                        </div>
                        <input type="number" id="price" name="price" value="<?php echo $item['price']; ?>" min="0" step="0.01" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>
                
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" value="<?php echo isset($item['stock']) ? $item['stock'] : '0'; ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Number of items in stock</p>
                </div>
                
                <div class="md:col-span-2">
                    <div class="flex items-center">
                        <input type="checkbox" id="available" name="available" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo $item['available'] ? 'checked' : ''; ?>>
                        <label for="available" class="ml-2 block text-sm text-gray-900">
                            Available for ordering
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Uncheck this if the item is temporarily unavailable</p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="index.php?page=menu" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category');
        const newCategoryContainer = document.getElementById('newCategoryContainer');
        const newCategoryInput = document.getElementById('new_category');
        const nameInput = document.getElementById('name');
        const codeInput = document.getElementById('code');
        const regenerateCodeBtn = document.getElementById('regenerateCode');
        
        // Category counts for code generation (from PHP)
        const categoryCodeCounts = <?php echo json_encode($categoryCodeCounts); ?>;
        
        // Existing codes to avoid duplicates
        const existingCodes = <?php echo json_encode($existingCodes); ?>;
        
        // Current code (for comparison)
        const currentCode = "<?php echo isset($item['code']) ? $item['code'] : ''; ?>";
        
        // Show/hide new category input based on selection
        categorySelect.addEventListener('change', function() {
            if (this.value === 'new') {
                newCategoryContainer.style.display = 'block';
                newCategoryInput.setAttribute('required', 'required');
            } else {
                newCategoryContainer.style.display = 'none';
                newCategoryInput.removeAttribute('required');
            }
        });
        
        // Regenerate code button
        regenerateCodeBtn.addEventListener('click', function() {
            generateCode(true); // Force regeneration
        });
        
        // Function to generate a unique item code
        function generateCode(forceNew = false) {
            const name = nameInput.value.trim();
            let category = categorySelect.value;
            
            // If "new category" is selected, use that input instead
            if (category === 'new') {
                category = newCategoryInput.value.trim();
            }
            
            // Only generate if we have both name and category and user wants to regenerate
            if (forceNew && name && category && category !== 'new') {
                // Get category prefix (first 3 letters, uppercase)
                let prefix = category.replace(/[^a-zA-Z0-9]/g, '').substring(0, 3).toUpperCase();
                if (!prefix) {
                    prefix = "ITM"; // Default if category doesn't yield usable characters
                }
                
                // Get the current count for this category
                let count = categoryCodeCounts[category] ? categoryCodeCounts[category] + 1 : 1;
                
                // Generate code with sequential number
                let code = prefix + String(count).padStart(3, '0');
                
                // Ensure code is unique
                let originalCode = code;
                let counter = 1;
                
                while (existingCodes.includes(code) || code === currentCode) {
                    code = originalCode + counter;
                    counter++;
                }
                
                // Set the code input value
                codeInput.value = code;
            }
        }
        
        // Initial check for category
        if (categorySelect.value === 'new') {
            newCategoryContainer.style.display = 'block';
            newCategoryInput.setAttribute('required', 'required');
        }
    });
</script>